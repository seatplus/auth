<?php

/*
 * MIT License
 *
 * Copyright (c) 2019, 2020 Felix Huber
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Seatplus\Auth\Http\Middleware\CheckRequiredScopes;
use Seatplus\Auth\Models\CharacterUser;
use Seatplus\Auth\Models\User;
use Seatplus\Auth\Services\SsoScopes\IsUserCompliantService;
use Seatplus\Eveapi\Models\Character\CharacterInfo;
use Seatplus\Eveapi\Models\RefreshToken;
use Seatplus\Eveapi\Models\SsoScopes;

beforeEach(function () {
    // $this->actingAs($this->test_user);

    mockRequest();

    Event::fake();
});

describe('redirect request', function () {
    it('if required scopes are missing', function () {
        // 1. Create RefreshToken for Character
        createRefreshTokenWithScopes(['a', 'b']);

        // 2. Create SsoScope (Corporation)
        createCorporationSsoScope([
            'character' => ['c'],
            'corporation' => [],
        ]);

        // TestingTime

        $this->actingAs($this->test_user);

        mockMiddleware();

        // Expect redirect
        $this->middleware->shouldReceive('redirectTo')->times(1);

        $this->middleware->handle($this->request, $this->next);
    });

    it('if required corporation role scopes is missing', function () {
        // 1. Create RefreshToken for Character
        createRefreshTokenWithScopes(['a', 'b']);

        // 2. Create SsoScope (Corporation)
        createCorporationSsoScope(['c']);

        // TestingTime

        $this->actingAs($this->test_user);

        mockMiddleware();

        // Expect redirect
        $this->middleware->shouldReceive('redirectTo')->times(1);

        $this->middleware->handle($this->request, $this->next);
    });

    it('if user scopes is missing', function () {
        // Create RefreshToken for Character
        createRefreshTokenWithScopes(['a', 'b']);

        // create user corporation scope
        createCorporationSsoScope(['a'], 'user');

        // to this point the middleware should pass no question asked

        // Create secondary character
        $secondary_character = Event::fakeFor(function () {
            $character_user = CharacterUser::factory()->make();
            $this->test_user->characterUsers()->save($character_user);

            return CharacterInfo::find($character_user->character_id);
        });

        // test that the test user owns both characters
        expect($this->test_user->refresh()->characters)->toHaveCount(2);

        // test that primary and secondary character has different corporations
        $this->assertNotEquals($this->test_character->corporation->corporation_id, $secondary_character->corporation->corporation_id);

        // create refresh_token for secondary character
        Event::fakeFor(function () use ($secondary_character) {
            $helper_token = RefreshToken::factory()->scopes(['c'])->make([
                'character_id' => $secondary_character->character_id,
            ]);

            $refresh_token = $secondary_character->refreshToken;
            $refresh_token->token = $helper_token->token;
            $refresh_token->save();
        });
        // at this point secondary character has scope c and misses scope a thus should result in an error

        // TestingTime

        $this->actingAs($this->test_user);

        mockMiddleware();

        // Expect redirect
        $this->middleware->shouldReceive('redirectTo')->times(1);

        $this->middleware->handle($this->request, $this->next);
    });

    it('if user misses global scopes', function () {
        // 1. Create RefreshToken for Character
        createRefreshTokenWithScopes(['a', 'b']);

        // 2. create global required scope
        SsoScopes::updateOrCreate(['type' => 'global'], ['selected_scopes' => ['c']]);

        // TestingTime

        $this->actingAs($this->test_user);

        mockMiddleware();

        // Expect redirect
        $this->middleware->shouldReceive('redirectTo')->times(1);

        $this->middleware->handle($this->request, $this->next);
    });

    it('if user application has not required scopes', function () {
        // 1. Create RefreshToken for Character
        createRefreshTokenWithScopes(['a', 'b']);

        // 2. create user application
        $this->test_user->application()->create(['id' => Str::uuid(), 'corporation_id' => $this->test_character->corporation->corporation_id]);

        // 3. create required corp scopes
        createCorporationSsoScope(['c']);

        // TestingTime

        $this->actingAs($this->test_user);

        mockMiddleware();

        // Expect redirect
        $this->middleware->shouldReceive('redirectTo')->times(1);

        $this->middleware->handle($this->request, $this->next);
    });
});

describe('passes middleware', function () {
    it('lets request through if no scopes are required', function () {
        createRefreshTokenWithScopes(['a', 'b']);

        $this->actingAs($this->test_user);

        mockMiddleware();

        // $this->middleware->shouldReceive('redirectTo')->once();
        $this->request->shouldReceive('forward')->times(1);

        $this->middleware->handle($this->request, $this->next);
    });

    it('if required scopes are present', function () {
        // 1. Create RefreshToken for Character
        createRefreshTokenWithScopes(['a', 'b']);

        // 2. Create SsoScope (Corporation)
        createCorporationSsoScope([
            'character' => ['a'],
            'corporation' => [],
        ]);

        // TestingTime

        $this->actingAs($this->test_user);

        mockMiddleware();

        // Expect 1 forward
        $this->request->shouldReceive('forward')->times(1);

        $this->middleware->handle($this->request, $this->next);
    });

    it('if required corporation role scopes is present', function () {
        // 1. Create RefreshToken for Character
        createRefreshTokenWithScopes(['a', 'b', 'esi-characters.read_corporation_roles.v1']);

        // 2. Create SsoScope (Corporation)
        createCorporationSsoScope([
            'character' => [],
            'corporation' => ['b'],
        ]);

        // TestingTime

        $this->actingAs($this->test_user);

        mockMiddleware();

        // Expect redirect
        $this->request->shouldReceive('forward')->times(1);

        $this->middleware->handle($this->request, $this->next);
    });

    it('if required global scopes are present', function () {
        // 1. Create RefreshToken for Character
        createRefreshTokenWithScopes(['a', 'b']);

        // 2. create global required sso scope
        SsoScopes::updateOrCreate(['type' => 'global'], ['selected_scopes' => ['a']]);

        // TestingTime

        $this->actingAs($this->test_user);

        mockMiddleware();

        // Expect 1 forward
        $this->request->shouldReceive('forward')->times(1);

        $this->middleware->handle($this->request, $this->next);
    });

    it('if user scopes is present', function () {
        // Create RefreshToken for Character
        createRefreshTokenWithScopes(['a', 'b']);

        // create user corporation scope
        createCorporationSsoScope(['a'], 'user');

        // to this point the middleware should pass no question asked

        // Create secondary character
        $secondary_character = Event::fakeFor(function () {
            $character_user = CharacterUser::factory()->make();
            $this->test_user->characterUsers()->save($character_user);

            return CharacterInfo::find($character_user->character_id);
        });

        // test that the test user owns both characters
        expect($this->test_user->refresh()->characters)->toHaveCount(2);

        // test that primary and secondary character has different corporations
        $this->assertNotEquals($this->test_character->corporation->corporation_id, $secondary_character->corporation->corporation_id);

        // update refresh_token for secondary character
        Event::fakeFor(function () use ($secondary_character) {
            $helper_token = RefreshToken::factory()->scopes(['a'])->make([
                'character_id' => $secondary_character->character_id,
            ]);

            $refresh_token = $secondary_character->refreshToken;
            $refresh_token->token = $helper_token->token;
            $refresh_token->save();
        });

        // at this point secondary character has scope a and scope a is required, thus should result in an forward

        // TestingTime

        $this->actingAs($this->test_user);

        mockMiddleware();

        // Expect redirect
        $this->request->shouldReceive('forward')->times(1);

        $this->middleware->handle($this->request, $this->next);
    });

    it('if user application has no required scopes', function () {
        // 1. Create RefreshToken for Character
        createRefreshTokenWithScopes(['a', 'b']);

        // 2. create user application
        $this->test_user->application()->create(['id' => Str::uuid(), 'corporation_id' => $this->test_character->corporation->corporation_id]);

        // TestingTime

        $this->actingAs($this->test_user);

        mockMiddleware();

        // Expect 1 forward
        $this->request->shouldReceive('forward')->times(1);

        $this->middleware->handle($this->request, $this->next);
    });

    it('lets request through if user application has required scopes', function () {
        // 1. Create RefreshToken for Character
        createRefreshTokenWithScopes(['a', 'b']);

        // 2. create user application
        $this->test_user->application()->create(['id' => Str::uuid(), 'corporation_id' => $this->test_character->corporation->corporation_id]);

        // 3. create required corp scopes
        createCorporationSsoScope(['a']);

        // TestingTime

        $this->actingAs($this->test_user);

        mockMiddleware();

        // Expect 1 forward
        $this->request->shouldReceive('forward')->times(1);

        $this->middleware->handle($this->request, $this->next);
    });
});

it('lets an unauthenticated request through', function () {
    // There is nobody to judge, and the compliance service takes a non-nullable User — so this would
    // otherwise be a TypeError on any route reached before authentication.
    $middleware = new CheckRequiredScopes;
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('user')->andReturnNull();

    $response = $middleware->handle($request, fn ($req) => response('OK'));

    expect($response->getContent())->toBe('OK');
});

it('redirects when user is not compliant', function () {
    $this->mock(IsUserCompliantService::class, function ($mock) {
        // One resolution now serves both the decision and redirectTo(), and it keeps the character each
        // missing scope belongs to.
        $mock->shouldReceive('getMissingCharacterScopes')
            ->once()
            ->with(Mockery::type(User::class))
            ->andReturn([[
                'character' => CharacterInfo::factory()->make(),
                'required_scopes' => ['scope1', 'scope2'],
                'missing_scopes' => ['scope1', 'scope2'],
            ]]);
    });

    $middleware = new CheckRequiredScopes(app(IsUserCompliantService::class));
    $request = Mockery::mock(Request::class);
    $request->shouldReceive('user')->andReturn(new User);

    $next = (fn ($req) => 'next');

    $response = $middleware->handle($request, $next);

    expect($response)->toBeInstanceOf(RedirectResponse::class);
    assert($response instanceof RedirectResponse);

    expect($response->getTargetUrl())->toBe('http://localhost');
});

// Helpers
function mockRequest(): void
{
    test()->request = mock(Request::class, function ($mock) {
        $mock->shouldReceive('user')->andReturn(test()->test_user);
    });

    test()->next = function ($request) {
        $request->forward();

        return response('OK');
    };
}

function mockMiddleware()
{
    test()->middleware = Mockery::mock(CheckRequiredScopes::class, [])
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();
}
