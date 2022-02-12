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
use Seatplus\Auth\Jobs\UserRolesSync;

it('works for non authed users', function () {
    $abstractUser = createSocialiteUser();

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn($abstractUser);

    Socialite::shouldReceive('driver')->with('eveonline')->andReturn($provider);

    test()->assertDatabaseMissing('refresh_tokens', [
        'character_id' => $abstractUser->character_id,
    ]);

    Event::fakeFor(function () {
        $response = test()->get(route('auth.eve.callback'))
           ->assertRedirect();
    });

    test()->assertDatabaseHas('refresh_tokens', [
        'character_id' => $abstractUser->character_id,
    ]);
});

it('returns error if scopes changed', function () {
    $abstractUser = createSocialiteUser();

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn($abstractUser);

    Socialite::shouldReceive('driver')->with('eveonline')->andReturn($provider);

    test()->actingAs(test()->test_user);

    session([
        'sso_scopes' => ['test'],
        'rurl' => '/home',
    ]);

    test()->get(route('auth.eve.callback'));

    expect(session('error'))->toBe('Something might have gone wrong. You might have changed the requested scopes on esi, please refer from doing so.');
});

test('one can add another character', function () {
    // Setup character user

    $abstractUser = createSocialiteUser(null, config('eveapi.scopes.minimum'));

    $provider = Mockery::mock(Provider::class);
    $provider->shouldReceive('user')->andReturn($abstractUser);

    Socialite::shouldReceive('driver')->with('eveonline')->andReturn($provider);

    // Mock Esi Response

    test()->actingAs(test()->test_user);

    session([
        'sso_scopes' => config('eveapi.scopes.minimum'),
        'rurl' => '/home',
    ]);

    // expect test_user only to have one character
    expect(test()->test_user->character_users)->toHaveCount(1);

    // assert no UserRolesSync job has been dispatched
    Queue::assertNothingPushed();

    $result = test()->get(route('auth.eve.callback'));

    // assert no UserRolesSync job has been dispatched
    Queue::assertPushedOn('high', UserRolesSync::class);

    // assert that no error is present
    expect(session('error'))->toBeNull();

    expect(session('success'))->toBe('Character added/updated successfully');

    expect(test()->test_user->refresh()->character_users)->toHaveCount(2);
});
