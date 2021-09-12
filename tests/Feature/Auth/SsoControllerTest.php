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

use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Laravel\Socialite\Contracts\Provider;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\User as SocialiteUser;
use Seatplus\Auth\Jobs\UserRolesSync;
use Seatplus\Auth\Tests\TestCase;
use Seatplus\Eveapi\Models\Character\CharacterInfo;
use Seatplus\Eveapi\Models\RefreshToken;

uses(TestCase::class);

it('works for non authed users', function () {
    $character_id = CharacterInfo::factory()->make()->character_id;

    $abstractUser = createSocialiteUser($character_id);

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn($abstractUser);

    Socialite::shouldReceive('driver')->with('eveonline')->andReturn($provider);

    test()->assertDatabaseMissing('refresh_tokens', [
        'character_id' => $character_id,
    ]);

    Event::fakeFor(function () {
        $response = test()->get(route('auth.eve.callback'))
           ->assertRedirect();
    });

    test()->assertDatabaseHas('refresh_tokens', [
        'character_id' => $character_id,
    ]);
});

it('returns error if scopes changed', function () {
    $character_id = Event::fakeFor(fn () => CharacterInfo::factory()->make()->character_id);

    $abstractUser = createSocialiteUser($character_id);

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn($abstractUser);

    Socialite::shouldReceive('driver')->with('eveonline')->andReturn($provider);

    test()->actingAs(test()->test_user);

    session([
        'sso_scopes' => ['test'],
        'rurl'       => '/home',
    ]);

    test()->get(route('auth.eve.callback'));

    test()->assertEquals(
        'Something might have gone wrong. You might have changed the requested scopes on esi, please refer from doing so.',
        session('error')
    );
});

test('one can add another character', function () {
    // Setup character user
    $character_id = Event::fakeFor(fn () => CharacterInfo::factory()->make()->character_id);

    $abstractUser = createSocialiteUser($character_id, 'refresh_token', implode(' ', config('eveapi.scopes.minimum')));

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn($abstractUser);

    Socialite::shouldReceive('driver')->with('eveonline')->andReturn($provider);

    // Mock Esi Response

    test()->actingAs(test()->test_user);

    session([
        'sso_scopes' => config('eveapi.scopes.minimum'),
        'rurl'       => '/home',
    ]);

    // assert no UserRolesSync job has been dispatched
    Queue::assertNothingPushed();

    $result = test()->get(route('auth.eve.callback'));

    // assert no UserRolesSync job has been dispatched
    Queue::assertPushedOn('high', UserRolesSync::class);

    // assert that no error is present
    expect(session('error'))->toBeNull();

    test()->assertEquals(
        'Character added/updated successfully',
        session('success')
    );

});

// Helpers
function createSocialiteUser($character_id, $refresh_token = 'refresh_token', $scopes = '1 2', $token = 'qq3dpeTMpDkjNasdasdewva3Be658eVVkox_1Ikodc')
{
    $socialiteUser = test()->createMock(SocialiteUser::class);
    $socialiteUser->character_id = $character_id;
    $socialiteUser->refresh_token = $refresh_token;
    $socialiteUser->character_owner_hash = sha1($token);
    $socialiteUser->scopes = $scopes;
    $socialiteUser->token = $token;
    $socialiteUser->expires_on = carbon('now')->addMinutes(15);

    return $socialiteUser;
}
