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
use Seatplus\Auth\Tests\TestCase;
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

        $this->middleware = Mockery::mock(CheckRequiredScopes::class, [])
            ->makePartial()
            ->shouldAllowMockingProtectedMethods();

        $this->mockRequest();

        Event::fake();

        $this->test_character = factory(CharacterInfo::class)->create([
            'character_id' => $this->test_user->character_users->first()->character_id,
        ]);
    }

    /** @test */
    public function it_lets_request_through_if_no_scopes_are_required()
    {
        $this->actingAs($this->test_user);

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
        $this->createCorporationSsoScope([
            'character'   => [],
            'corporation' => ['b'],
        ]);

        // TestingTime

        $this->actingAs($this->test_user);

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

        //Expect redirect
        $this->request->shouldReceive('forward')->times(1);

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
            factory(RefreshToken::class)->create([
                'character_id' => $this->test_character->character_id,
                'scopes'       => $array,
            ]);
        });
    }

    private function createCorporationSsoScope(array $array)
    {
        factory(SsoScopes::class)->create([
            'selected_scopes' => $array,
            'morphable_id'    => $this->test_character->corporation->corporation_id,
            'morphable_type'  => CorporationInfo::class,
        ]);
    }
}
