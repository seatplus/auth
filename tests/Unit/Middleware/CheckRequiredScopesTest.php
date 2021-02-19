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

namespace Seatplus\Auth\Tests\Unit\Middleware;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Mockery;
use Seatplus\Auth\Http\Middleware\CheckRequiredScopes;
use Seatplus\Auth\Models\CharacterUser;
use Seatplus\Auth\Tests\TestCase;
use Seatplus\Eveapi\Models\Character\CharacterAffiliation;
use Seatplus\Eveapi\Models\Character\CharacterInfo;
use Seatplus\Eveapi\Models\Corporation\CorporationInfo;
use Seatplus\Eveapi\Models\RefreshToken;
use Seatplus\Eveapi\Models\SsoScopes;

class CheckRequiredScopesTest extends TestCase
{
    /**
     * @var \Mockery\Mock
     */
    private $middleware;

    /**
     * @var \Mockery\LegacyMockInterface|\Mockery\MockInterface
     */
    private $request;

    /**
     * @var \Closure
     */
    private $next;

    public function setUp(): void
    {
        parent::setUp();

        //$this->actingAs($this->test_user);

        $this->mockRequest();

        Event::fake();
    }

    /** @test */
    public function it_lets_request_through_if_no_scopes_are_required()
    {
        $this->createRefreshTokenWithScopes(['a', 'b']);

        $this->actingAs($this->test_user);

        $this->mockMiddleware();

        //$this->middleware->shouldReceive('redirectTo')->once();
        $this->request->shouldReceive('forward')->times(1);

        $this->middleware->handle($this->request, $this->next);
    }

    /** @test */
    public function it_lets_request_through_if_required_scopes_are_present()
    {

        // 1. Create RefreshToken for Character
        $this->createRefreshTokenWithScopes(['a', 'b']);

        // 2. Create SsoScope (Corporation)
        $this->createCorporationSsoScope([
            'character'   => ['a'],
            'corporation' => [],
        ]);

        // TestingTime

        $this->actingAs($this->test_user);

        $this->mockMiddleware();

        //Expect 1 forward
        $this->request->shouldReceive('forward')->times(1);

        $this->middleware->handle($this->request, $this->next);
    }

    /** @test */
    public function it_stops_request_if_required_scopes_are_missing()
    {

        // 1. Create RefreshToken for Character
        $this->createRefreshTokenWithScopes(['a', 'b']);

        // 2. Create SsoScope (Corporation)
        $this->createCorporationSsoScope([
            'character'   => ['c'],
            'corporation' => [],
        ]);

        // TestingTime

        $this->actingAs($this->test_user);

        $this->mockMiddleware();

        //Expect redirect
        $this->middleware->shouldReceive('redirectTo')->times(1);

        $this->middleware->handle($this->request, $this->next);
    }

    /** @test */
    public function it_stops_request_if_required_corporation_role_scopes_is_missing()
    {

        // 1. Create RefreshToken for Character
        $this->createRefreshTokenWithScopes(['a', 'b']);

        // 2. Create SsoScope (Corporation)
        $this->createCorporationSsoScope(['c']);

        // TestingTime

        $this->actingAs($this->test_user);

        $this->mockMiddleware();

        //Expect redirect
        $this->middleware->shouldReceive('redirectTo')->times(1);

        $this->middleware->handle($this->request, $this->next);
    }

    /** @test */
    public function it_lets_request_through_if_required_corporation_role_scopes_is_present()
    {

        // 1. Create RefreshToken for Character
        $this->createRefreshTokenWithScopes(['a', 'b', 'esi-characters.read_corporation_roles.v1']);

        // 2. Create SsoScope (Corporation)
        $this->createCorporationSsoScope([
            'character'   => [],
            'corporation' => ['b'],
        ]);

        // TestingTime

        $this->actingAs($this->test_user);

        $this->mockMiddleware();

        //Expect redirect
        $this->request->shouldReceive('forward')->times(1);

        $this->middleware->handle($this->request, $this->next);
    }

    /** @test */
    public function it_forwards_request_if_user_misses_global_scopes()
    {
        // 1. Create RefreshToken for Character
        $this->createRefreshTokenWithScopes(['a', 'b']);

        // 2. create global required scope
        SsoScopes::updateOrCreate(['type' => 'global'], ['selected_scopes' => ['c']]);

        // TestingTime

        $this->actingAs($this->test_user);

        $this->mockMiddleware();

        //Expect redirect
        $this->middleware->shouldReceive('redirectTo')->times(1);

        $this->middleware->handle($this->request, $this->next);
    }

    /** @test */
    public function it_lets_request_through_if_required_global_scopes_are_present()
    {

        // 1. Create RefreshToken for Character
        $this->createRefreshTokenWithScopes(['a', 'b']);

        // 2. create global required sso scope
        SsoScopes::updateOrCreate(['type' => 'global'], ['selected_scopes' => ['a']]);

        // TestingTime

        $this->actingAs($this->test_user);

        $this->mockMiddleware();

        //Expect 1 forward
        $this->request->shouldReceive('forward')->times(1);

        $this->middleware->handle($this->request, $this->next);
    }

    /** @test */
    public function it_stops_request_if_user_scopes_is_missing()
    {

        // Create RefreshToken for Character
        $this->createRefreshTokenWithScopes(['a', 'b']);

        // create user corporation scope
        $this->createCorporationSsoScope(['a'], 'user');

        // to this point the middleware should pass no question asked

        // Create secondary character
        $secondary_character = Event::fakeFor(function () {

            $character_user = CharacterUser::factory()->make();
            $this->test_user->character_users()->save($character_user);

            return CharacterInfo::find($character_user->character_id);
        });

        // test that the test user owns both characters
        $this->assertCount(2, $this->test_user->refresh()->characters);

        // test that primary and secondary character has different corporations
        $this->assertNotEquals($this->test_character->corporation->corporation_id, $secondary_character->corporation->corporation_id);

        // create refresh_token for secondary character
        Event::fakeFor(function () use ($secondary_character) {
            $refresh_token = $secondary_character->refresh_token;
            $refresh_token->scopes = ['c'];
            $refresh_token->save();
        });

        // at this point secondary character has scope c and misses scope a thus should result in an error

        // TestingTime

        $this->actingAs($this->test_user);

        $this->mockMiddleware();

        //Expect redirect
        $this->middleware->shouldReceive('redirectTo')->times(1);

        $this->middleware->handle($this->request, $this->next);
    }

    /** @test */
    public function it_lets_request_through_if_user_scopes_is_present()
    {

        // Create RefreshToken for Character
        $this->createRefreshTokenWithScopes(['a', 'b']);

        // create user corporation scope
        $this->createCorporationSsoScope(['a'], 'user');

        // to this point the middleware should pass no question asked

        // Create secondary character
        $secondary_character = Event::fakeFor(function () {

            $character_user = CharacterUser::factory()->make();
            $this->test_user->character_users()->save($character_user);

            return CharacterInfo::find($character_user->character_id);
        });

        // test that the test user owns both characters
        $this->assertCount(2, $this->test_user->refresh()->characters);

        // test that primary and secondary character has different corporations
        $this->assertNotEquals($this->test_character->corporation->corporation_id, $secondary_character->corporation->corporation_id);

        // update refresh_token for secondary character
        Event::fakeFor(function () use ($secondary_character) {
            $refresh_token = $secondary_character->refresh_token;
            $refresh_token->scopes = ['a'];
            $refresh_token->save();
        });

        // at this point secondary character has scope a and scope a is required, thus should result in an forward

        // TestingTime

        $this->actingAs($this->test_user);

        $this->mockMiddleware();

        //Expect redirect
        $this->request->shouldReceive('forward')->times(1);

        $this->middleware->handle($this->request, $this->next);
    }

    /** @test */
    public function it_lets_request_through_if_user_application_has_no_required_scopes()
    {
        // 1. Create RefreshToken for Character
        $this->createRefreshTokenWithScopes(['a', 'b']);

        // 2. create user application
        $this->test_user->application()->create(['corporation_id' =>  $this->test_character->corporation->corporation_id]);

        // TestingTime

        $this->actingAs($this->test_user);

        $this->mockMiddleware();

        //Expect 1 forward
        $this->request->shouldReceive('forward')->times(1);

        $this->middleware->handle($this->request, $this->next);
    }

    /** @test */
    public function it_lets_request_through_if_user_application_has_required_scopes()
    {
        // 1. Create RefreshToken for Character
        $this->createRefreshTokenWithScopes(['a', 'b']);

        // 2. create user application
        $this->test_user->application()->create(['corporation_id' =>  $this->test_character->corporation->corporation_id]);

        // 3. create required corp scopes
        $this->createCorporationSsoScope(['a']);

        // TestingTime

        $this->actingAs($this->test_user);

        $this->mockMiddleware();

        //Expect 1 forward
        $this->request->shouldReceive('forward')->times(1);

        $this->middleware->handle($this->request, $this->next);
    }

    /** @test */
    public function it_forwards_request_if_user_application_has_not_required_scopes()
    {
        // 1. Create RefreshToken for Character
        $this->createRefreshTokenWithScopes(['a', 'b']);

        // 2. create user application
        $this->test_user->application()->create(['corporation_id' =>  $this->test_character->corporation->corporation_id]);

        // 3. create required corp scopes
        $this->createCorporationSsoScope(['c']);

        // TestingTime

        $this->actingAs($this->test_user);

        $this->mockMiddleware();

        //Expect redirect
        $this->middleware->shouldReceive('redirectTo')->times(1);

        $this->middleware->handle($this->request, $this->next);
    }

    private function mockRequest(): void
    {
        $this->request = Mockery::mock(Request::class);

        $this->next = function ($request) {
            $request->forward();
        };
    }

    private function createRefreshTokenWithScopes(array $array)
    {
        Event::fakeFor(function () use ($array) {

            if($this->test_character->refresh_token) {

                $refresh_token = $this->test_character->refresh_token;
                $refresh_token->scopes = $array;
                $refresh_token->save();

                return;
            }


            RefreshToken::factory()->create([
                'character_id' => $this->test_character->character_id,
                'scopes'       => $array,
            ]);
        });
    }

    private function createCorporationSsoScope(array $array, string $type = 'default')
    {
        SsoScopes::factory()->create([
            'selected_scopes' => $array,
            'morphable_id'    => $this->test_character->corporation->corporation_id,
            'morphable_type'  => CorporationInfo::class,
            'type' => $type
        ]);
    }

    private function mockMiddleware()
    {
        $this->middleware = Mockery::mock(CheckRequiredScopes::class, [])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();
    }
}
