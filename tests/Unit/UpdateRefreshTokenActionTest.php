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
use Seatplus\Auth\Http\Actions\Sso\UpdateRefreshTokenAction;
use Seatplus\Eveapi\Models\RefreshToken;

test('create refresh token', function () {
    $eve_data = createEveUser(test()->test_user->id);

    $action = new UpdateRefreshTokenAction();
    Event::fakeFor(fn() => $action($eve_data));

    test()->assertDatabaseHas('refresh_tokens', [
        'character_id' => test()->test_user->id,
    ]);
});

it('does update refresh token active sessions', function () {
    test()->actingAs(test()->test_user);

    // create RefreshToken
    $eveUser = createEveUser();

    $action = new UpdateRefreshTokenAction();
    Event::fakeFor(fn() => $action($eveUser));

    test()->assertDatabaseHas('refresh_tokens', [
        'character_id'  => $eveUser->character_id,
        'refresh_token' => $eveUser->refreshToken,
    ]);

    // Change RefreshToken
    $eveUser_changedRefreshToken = createEveUser(
        $eveUser->character_id,
        $eveUser->character_owner_hash
    );

    Event::fakeFor(fn() => $action($eveUser_changedRefreshToken));

    test()->assertDatabaseHas('refresh_tokens', [
        'character_id'  => $eveUser->character_id,
        'refresh_token' => $eveUser_changedRefreshToken->refreshToken,
    ]);
});

it('does not update refresh token for new session of a valid refresh token user', function () {
    // create RefreshToken
    $eveUser = createEveUser();

    $action = new UpdateRefreshTokenAction();
    Event::fakeFor(fn() => $action($eveUser));

    test()->assertDatabaseHas('refresh_tokens', [
        'character_id'  => $eveUser->character_id,
        'refresh_token' => $eveUser->refreshToken,
    ]);

    // Change RefreshToken

    $eveUser_changedRefreshToken = createEveUser(
        $eveUser->character_id,
        $eveUser->character_owner_hash
    );

    Event::fakeFor(fn() => $action($eveUser));

    test()->assertDatabaseMissing('refresh_tokens', [
        'character_id'  => $eveUser->character_id,
        'refresh_token' => $eveUser_changedRefreshToken->refreshToken,
    ]);
});

test('restore trashed refresh token', function () {
    // create RefreshToken
    $eveUser = createEveUser();

    $action = new UpdateRefreshTokenAction();
    Event::fakeFor(fn() => $action($eveUser));

    test()->assertDatabaseHas('refresh_tokens', [
        'character_id'  => $eveUser->character_id,
        'refresh_token' => $eveUser->refreshToken,
    ]);

    $refresh_token = RefreshToken::find($eveUser->character_id);

    // Assert if RefreshToken was created
    expect($refresh_token)
        ->character_id->toBe((string) $eveUser->character_id)
        ->not()->toBeEmpty();

    // SoftDelete RefreshToken
    $refresh_token->delete();

    test()->assertSoftDeleted(RefreshToken::find($eveUser->character_id));

    // Recreate RefreshToken
    $eveUser_changedRefreshToken = createEveUser(
        $eveUser->character_id,
        $eveUser->character_owner_hash
    );

    Event::fakeFor(fn() => $action($eveUser_changedRefreshToken));

    expect(RefreshToken::find($eveUser->character_id))->not()->toBeEmpty();

    test()->assertDatabaseHas('refresh_tokens', [
        'character_id'  => $eveUser->character_id,
        'refresh_token' => $eveUser_changedRefreshToken->refreshToken,
    ]);
});

