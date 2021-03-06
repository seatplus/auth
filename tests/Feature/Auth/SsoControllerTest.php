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
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Mockery;
use Seatplus\Auth\Tests\TestCase;
use Seatplus\Eveapi\Models\Character\CharacterInfo;
use Seatplus\Eveapi\Models\RefreshToken;

class SsoControllerTest extends TestCase
{

    /** @test */
    public function it_works_for_non_authed_users()
    {
        $character_id = CharacterInfo::factory()->make()->character_id;

        $abstractUser = $this->createSocialiteUser($character_id);

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($abstractUser);

        Socialite::shouldReceive('driver')->with('eveonline')->andReturn($provider);

        $this->assertDatabaseMissing('refresh_tokens', [
            'character_id' => $character_id,
        ]);

        Event::fakeFor(function () {
            $response = $this->get(route('auth.eve.callback'))
                ->assertRedirect();
        });

        $this->assertDatabaseHas('refresh_tokens', [
            'character_id' => $character_id,
        ]);
    }

    /** @test */
    public function it_returns_error_if_scopes_changed()
    {
        $character_id = Event::fakeFor(fn () => CharacterInfo::factory()->make()->character_id);

        $abstractUser = $this->createSocialiteUser($character_id);

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($abstractUser);

        Socialite::shouldReceive('driver')->with('eveonline')->andReturn($provider);

        $this->actingAs($this->test_user);

        session([
            'sso_scopes' => ['test'],
            'rurl'       => '/home',
        ]);

        $this->get(route('auth.eve.callback'));

        $this->assertEquals(
            'Something might have gone wrong. You might have changed the requested scopes on esi, please refer from doing so.',
            session('error')
        );
    }

    /** @test */
    public function one_can_add_another_character()
    {
        // Setup character user
        $character_id = Event::fakeFor(fn () => CharacterInfo::factory()->make()->character_id);

        $abstractUser = $this->createSocialiteUser($character_id, 'refresh_token', implode(' ', config('eveapi.scopes.minimum')));

        $provider = Mockery::mock(Provider::class);
        $provider->shouldReceive('user')->andReturn($abstractUser);

        Socialite::shouldReceive('driver')->with('eveonline')->andReturn($provider);

        // Mock Esi Response

        $this->actingAs($this->test_user);

        session([
            'sso_scopes' => config('eveapi.scopes.minimum'),
            'rurl'       => '/home',
        ]);

        $result = $this->get(route('auth.eve.callback'));

        $this->assertNull(session('error'));

        $this->assertEquals(
            'Character added/updated successfully',
            session('success')
        );
    }

    private function createSocialiteUser($character_id, $refresh_token = 'refresh_token', $scopes = '1 2', $token = 'qq3dpeTMpDkjNasdasdewva3Be658eVVkox_1Ikodc')
    {
        $socialiteUser = $this->createMock(SocialiteUser::class);
        $socialiteUser->character_id = $character_id;
        $socialiteUser->refresh_token = $refresh_token;
        $socialiteUser->character_owner_hash = sha1($token);
        $socialiteUser->scopes = $scopes;
        $socialiteUser->token = $token;
        $socialiteUser->expires_on = carbon('now')->addMinutes(15);

        return $socialiteUser;
    }
}
