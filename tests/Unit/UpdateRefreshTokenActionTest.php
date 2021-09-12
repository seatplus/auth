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
use Laravel\Socialite\Two\User as SocialiteUser;
use Seatplus\Auth\Http\Actions\Sso\UpdateRefreshTokenAction;
use Seatplus\Auth\Tests\TestCase;
use Seatplus\Eveapi\Models\RefreshToken;

uses(TestCase::class);

test('create refresh token', function () {
    $eve_data = createSocialiteUser(test()->test_user->id);

    Event::fakeFor(function () use ($eve_data) {
        (new UpdateRefreshTokenAction())->execute($eve_data);
    });

    test()->assertDatabaseHas('refresh_tokens', [
        'character_id' => test()->test_user->id,
    ]);
});

it('does update refresh token active sessions', function () {
    test()->actingAs(test()->test_user);

    // create RefreshToken
    $eve_data = createSocialiteUser(test()->test_user->id);

    Event::fakeFor(function () use ($eve_data) {
        (new UpdateRefreshTokenAction())->execute($eve_data);
    });

    test()->assertDatabaseHas('refresh_tokens', [
        'character_id'  => test()->test_user->id,
        'refresh_token' => 'refresh_token',
    ]);

    test()->actingAs(test()->test_user);
    // Change RefreshToken

    $eve_data = createSocialiteUser(test()->test_user->id, 'new_refreshToken');

    (new UpdateRefreshTokenAction())->execute($eve_data);

    test()->assertDatabaseHas('refresh_tokens', [
        'character_id'  => test()->test_user->id,
        'refresh_token' => 'new_refreshToken',
    ]);
});

it('does not update refresh token for new session of a valid refresh token user', function () {
    // create RefreshToken
    $eve_data = createSocialiteUser(test()->test_user->id);

    Event::fakeFor(function () use ($eve_data) {
        RefreshToken::factory()->create([
            'character_id'  => test()->test_user->id,
            'refresh_token' => 'refresh_token',
        ]);

        (new UpdateRefreshTokenAction())->execute($eve_data);
    });

    test()->assertDatabaseHas('refresh_tokens', [
        'character_id'  => test()->test_user->id,
        'refresh_token' => 'refresh_token',
    ]);

    // Change RefreshToken

    $eve_data = createSocialiteUser(test()->test_user->id, 'new_refreshToken');

    (new UpdateRefreshTokenAction())->execute($eve_data);

    test()->assertDatabaseMissing('refresh_tokens', [
        'character_id'  => test()->test_user->id,
        'refresh_token' => 'new_refreshToken',
    ]);
});

test('restore trashed refresh token', function () {
    // create RefreshToken
    $eve_data = createSocialiteUser(test()->test_user->id);

    Event::fakeFor(function () use ($eve_data) {
        (new UpdateRefreshTokenAction())->execute($eve_data);
    });

    test()->assertDatabaseHas('refresh_tokens', [
        'character_id' => test()->test_user->id,
    ]);

    // Assert if RefreshToken was created
    $refresh_token = RefreshToken::find(test()->test_user->id);

    test()->assertNotEmpty($refresh_token);

    // SoftDelete RefreshToken
    $refresh_token->delete();

    test()->assertSoftDeleted(RefreshToken::find(test()->test_user->id));

    // Recreate RefreshToken
    $eve_data = createSocialiteUser(test()->test_user->id, 'newRefreshToken');
    (new UpdateRefreshTokenAction())->execute($eve_data);
    test()->assertNotEmpty(RefreshToken::find(test()->test_user->id));
    test()->assertDatabaseHas('refresh_tokens', [
        'character_id'  => test()->test_user->id,
        'refresh_token' => 'newRefreshToken',
    ]);
});

// Helpers
function createSocialiteUser($character_id, $refresh_token = 'refresh_token', $scopes = '1 2', $token = 'qq3dpeTMpDkjNasdasdewva3Be658eVVkox_1Ikodc')
{
    $socialiteUser = test()->createMock(SocialiteUser::class);
    $socialiteUser->character_id = $character_id;
    $socialiteUser->refresh_token = $refresh_token;
    $socialiteUser->scopes = $scopes;
    $socialiteUser->token = $token;
    $socialiteUser->expires_on = carbon('now')->addMinutes(15);

    return $socialiteUser;
}
