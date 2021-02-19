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

namespace Seatplus\Auth\Tests\Feature\Auth;

use Illuminate\Support\Facades\Event;
use Laravel\Socialite\Contracts\Factory;
use Laravel\Socialite\Facades\Socialite;
use Seatplus\Auth\Tests\TestCase;
use Seatplus\Eveapi\Models\Character\CharacterInfo;
use Seatplus\Eveapi\Models\Corporation\CorporationInfo;
use Seatplus\Eveapi\Models\RefreshToken;
use Seatplus\Eveapi\Models\SsoScopes;

class StepUpTest extends TestCase
{
    public function setUp(): void
    {
        parent::setUp();

        Event::fake();

        //$this->test_character =

        //\Mockery::mock(Factory::class)->shouldReceive('driver')->andReturn('');
        Socialite::shouldReceive('driver->scopes->redirect')->andReturn('');
    }

    /** @test */
    public function one_can_request_another_scope()
    {

        // 1. Create refresh_token
        $this->createRefreshTokenWithScopes(['a', 'b']);

        // 2. Create SsoScope (Corporation)
        /*$this->createCorporationSsoScope([
            'character'   => ['a'],
            'corporation' => [],
        ]);*/

        $add_scopes = implode(',', ['1', '2']);

        $response = $this->actingAs($this->test_user)->get(route('auth.eve.step_up', [
            'character_id' => $this->test_character->character_id,
            'add_scopes'   => $add_scopes,
        ]));

        $this->assertEquals($this->test_character->character_id, session('step_up'));
        $this->assertEquals(['a', 'b', '1', '2'], session('sso_scopes'));
    }

    private function createCorporationSsoScope(array $array)
    {
        SsoScopes::factory()->create([
            'selected_scopes' => $array,
            'morphable_id'    => $this->test_character->corporation->corporation_id,
            'morphable_type'  => CorporationInfo::class,
        ]);
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
}
