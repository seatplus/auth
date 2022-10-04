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

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Seatplus\Auth\Http\Middleware\CheckRequiredScopes;
use Seatplus\Auth\Models\CharacterUser;
use Seatplus\Eveapi\Models\Character\CharacterInfo;
use Seatplus\Eveapi\Models\RefreshToken;
use Seatplus\Eveapi\Models\SsoScopes;

beforeEach(function () {
    //test()->actingAs(test()->test_user);

    mockRequest();

    Event::fake();
});

it('lets request through if no scopes are required', function () {
    createRefreshTokenWithScopes(['a', 'b']);

    test()->actingAs(test()->test_user);

    mockMiddleware();

    //test()->middleware->shouldReceive('redirectTo')->once();
    test()->request->shouldReceive('forward')->times(1);

    test()->middleware->handle(test()->request, test()->next);
});

it('lets request through if required scopes are present', function () {
    // 1. Create RefreshToken for Character
    createRefreshTokenWithScopes(['a', 'b']);

    // 2. Create SsoScope (Corporation)
    createCorporationSsoScope([
        'character' => ['a'],
        'corporation' => [],
    ]);

    // TestingTime

    test()->actingAs(test()->test_user);

    mockMiddleware();

    //Expect 1 forward
    test()->request->shouldReceive('forward')->times(1);

    test()->middleware->handle(test()->request, test()->next);
});

it('stops request if required scopes are missing', function () {
    // 1. Create RefreshToken for Character
    createRefreshTokenWithScopes(['a', 'b']);

    // 2. Create SsoScope (Corporation)
    createCorporationSsoScope([
        'character' => ['c'],
        'corporation' => [],
    ]);

    // TestingTime

    test()->actingAs(test()->test_user);

    mockMiddleware();

    //Expect redirect
    test()->middleware->shouldReceive('redirectTo')->times(1);

    test()->middleware->handle(test()->request, test()->next);
});

it('stops request if required corporation role scopes is missing', function () {
    // 1. Create RefreshToken for Character
    createRefreshTokenWithScopes(['a', 'b']);

    // 2. Create SsoScope (Corporation)
    createCorporationSsoScope(['c']);

    // TestingTime

    test()->actingAs(test()->test_user);

    mockMiddleware();

    //Expect redirect
    test()->middleware->shouldReceive('redirectTo')->times(1);

    test()->middleware->handle(test()->request, test()->next);
});

it('lets request through if required corporation role scopes is present', function () {
    // 1. Create RefreshToken for Character
    createRefreshTokenWithScopes(['a', 'b', 'esi-characters.read_corporation_roles.v1']);

    // 2. Create SsoScope (Corporation)
    createCorporationSsoScope([
        'character' => [],
        'corporation' => ['b'],
    ]);

    // TestingTime

    test()->actingAs(test()->test_user);

    mockMiddleware();

    //Expect redirect
    test()->request->shouldReceive('forward')->times(1);

    test()->middleware->handle(test()->request, test()->next);
});

it('forwards request if user misses global scopes', function () {
    // 1. Create RefreshToken for Character
    createRefreshTokenWithScopes(['a', 'b']);

    // 2. create global required scope
    SsoScopes::updateOrCreate(['type' => 'global'], ['selected_scopes' => ['c']]);

    // TestingTime

    test()->actingAs(test()->test_user);

    mockMiddleware();

    //Expect redirect
    test()->middleware->shouldReceive('redirectTo')->times(1);

    test()->middleware->handle(test()->request, test()->next);
});

it('lets request through if required global scopes are present', function () {
    // 1. Create RefreshToken for Character
    createRefreshTokenWithScopes(['a', 'b']);

    // 2. create global required sso scope
    SsoScopes::updateOrCreate(['type' => 'global'], ['selected_scopes' => ['a']]);

    // TestingTime

    test()->actingAs(test()->test_user);

    mockMiddleware();

    //Expect 1 forward
    test()->request->shouldReceive('forward')->times(1);

    test()->middleware->handle(test()->request, test()->next);
});

it('stops request if user scopes is missing', function () {
    // Create RefreshToken for Character
    createRefreshTokenWithScopes(['a', 'b']);

    // create user corporation scope
    createCorporationSsoScope(['a'], 'user');

    // to this point the middleware should pass no question asked

    // Create secondary character
    $secondary_character = Event::fakeFor(function () {
        $character_user = CharacterUser::factory()->make();
        test()->test_user->character_users()->save($character_user);

        return CharacterInfo::find($character_user->character_id);
    });

    // test that the test user owns both characters
    expect(test()->test_user->refresh()->characters)->toHaveCount(2);

    // test that primary and secondary character has different corporations
    test()->assertNotEquals(test()->test_character->corporation->corporation_id, $secondary_character->corporation->corporation_id);

    // create refresh_token for secondary character
    Event::fakeFor(function () use ($secondary_character) {
        $helper_token = RefreshToken::factory()->scopes(['c'])->make([
            'character_id' => $secondary_character->character_id,
        ]);

        $refresh_token = $secondary_character->refresh_token;
        $refresh_token->token = $helper_token->token;
        $refresh_token->save();
    });

    // at this point secondary character has scope c and misses scope a thus should result in an error

    // TestingTime

    test()->actingAs(test()->test_user);

    mockMiddleware();

    //Expect redirect
    test()->middleware->shouldReceive('redirectTo')->times(1);

    test()->middleware->handle(test()->request, test()->next);
});

it('lets request through if user scopes is present', function () {
    // Create RefreshToken for Character
    createRefreshTokenWithScopes(['a', 'b']);

    // create user corporation scope
    createCorporationSsoScope(['a'], 'user');

    // to this point the middleware should pass no question asked

    // Create secondary character
    $secondary_character = Event::fakeFor(function () {
        $character_user = CharacterUser::factory()->make();
        test()->test_user->character_users()->save($character_user);

        return CharacterInfo::find($character_user->character_id);
    });

    // test that the test user owns both characters
    expect(test()->test_user->refresh()->characters)->toHaveCount(2);

    // test that primary and secondary character has different corporations
    test()->assertNotEquals(test()->test_character->corporation->corporation_id, $secondary_character->corporation->corporation_id);

    // update refresh_token for secondary character
    Event::fakeFor(function () use ($secondary_character) {
        $helper_token = RefreshToken::factory()->scopes(['a'])->make([
            'character_id' => $secondary_character->character_id,
        ]);

        $refresh_token = $secondary_character->refresh_token;
        $refresh_token->token = $helper_token->token;
        $refresh_token->save();
    });

    // at this point secondary character has scope a and scope a is required, thus should result in an forward

    // TestingTime

    test()->actingAs(test()->test_user);

    mockMiddleware();

    //Expect redirect
    test()->request->shouldReceive('forward')->times(1);

    test()->middleware->handle(test()->request, test()->next);
});

it('lets request through if user application has no required scopes', function () {
    // 1. Create RefreshToken for Character
    createRefreshTokenWithScopes(['a', 'b']);

    // 2. create user application
    test()->test_user->application()->create(['id' => \Illuminate\Support\Str::uuid(), 'corporation_id' => test()->test_character->corporation->corporation_id]);

    // TestingTime

    test()->actingAs(test()->test_user);

    mockMiddleware();

    //Expect 1 forward
    test()->request->shouldReceive('forward')->times(1);

    test()->middleware->handle(test()->request, test()->next);
});

it('lets request through if user application has required scopes', function () {
    // 1. Create RefreshToken for Character
    createRefreshTokenWithScopes(['a', 'b']);

    // 2. create user application
    test()->test_user->application()->create(['id' => \Illuminate\Support\Str::uuid(), 'corporation_id' => test()->test_character->corporation->corporation_id]);

    // 3. create required corp scopes
    createCorporationSsoScope(['a']);

    // TestingTime

    test()->actingAs(test()->test_user);

    mockMiddleware();

    //Expect 1 forward
    test()->request->shouldReceive('forward')->times(1);

    test()->middleware->handle(test()->request, test()->next);
});

it('forwards request if user application has not required scopes', function () {
    // 1. Create RefreshToken for Character
    createRefreshTokenWithScopes(['a', 'b']);

    // 2. create user application
    test()->test_user->application()->create(['id' => \Illuminate\Support\Str::uuid(), 'corporation_id' => test()->test_character->corporation->corporation_id]);

    // 3. create required corp scopes
    createCorporationSsoScope(['c']);

    // TestingTime

    test()->actingAs(test()->test_user);

    mockMiddleware();

    //Expect redirect
    test()->middleware->shouldReceive('redirectTo')->times(1);

    test()->middleware->handle(test()->request, test()->next);
});

it('caches characters_with_missing_scopes', function () {
    // 1. Create RefreshToken for Character
    createRefreshTokenWithScopes(['a', 'b']);

    // 2. create user application
    test()->test_user->application()->create(['id' => \Illuminate\Support\Str::uuid(), 'corporation_id' => test()->test_character->corporation->corporation_id]);

    // 3. create required corp scopes
    createCorporationSsoScope(['c']);

    // TestingTime

    test()->actingAs(test()->test_user);

    mockMiddleware();

    //Expect redirect
    test()->middleware->shouldReceive('redirectTo')->times(1);

    Cache::shouldReceive('tags')
        ->with(['characters_with_missing_scopes', test()->test_user->id])
        ->andReturnSelf();
    Cache::shouldReceive('get')->andReturnNull();
    Cache::shouldReceive('put')
        ->once();

    test()->middleware->handle(test()->request, test()->next);
});

it('it get caches characters_with_missing_scopes', function () {
    // prepare
    test()->actingAs(test()->test_user);
    $user_id = test()->test_user->id;
    $cache_key = "UserScopes:${user_id}";

    mockMiddleware();

    Cache::shouldReceive('tags')
        ->with(['characters_with_missing_scopes', $user_id])
        ->andReturnSelf();

    Cache::shouldReceive('get')->with($cache_key)->andReturn(collect(['foo' => 'bar']));

    Cache::shouldReceive('put')->never();

    //Expect redirect
    test()->middleware->shouldReceive('redirectTo')->times(1);

    // test
    test()->middleware->handle(test()->request, test()->next);
});

// Helpers
function mockRequest(): void
{
    test()->request = Mockery::mock(Request::class);

    test()->next = function ($request) {
        $request->forward();
    };
}

function mockMiddleware()
{
    test()->middleware = Mockery::mock(CheckRequiredScopes::class, [])
        ->makePartial()
        ->shouldAllowMockingProtectedMethods();
}
