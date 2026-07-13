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
use Seatplus\Auth\Containers\EveUser;
use Seatplus\Auth\Http\Actions\Sso\UpdateRefreshTokenAction;
use Seatplus\Eveapi\Models\RefreshToken;

test('create refresh token', function () {
    $eve_data = createEveUser(test()->test_user->id);

    $action = new UpdateRefreshTokenAction;
    Event::fakeFor(fn () => $action($eve_data));

    test()->assertDatabaseHas('refresh_tokens', [
        'character_id' => test()->test_user->id,
    ]);
});

it('does update refresh token active sessions', function () {
    test()->actingAs(test()->test_user);

    // create RefreshToken
    $eveUser = createEveUser();

    $action = new UpdateRefreshTokenAction;
    Event::fakeFor(fn () => $action($eveUser));

    test()->assertDatabaseHas('refresh_tokens', [
        'character_id' => $eveUser->character_id,
        'refresh_token' => $eveUser->refreshToken,
    ]);

    // Change RefreshToken
    $eveUser_changedRefreshToken = createEveUser(
        $eveUser->character_id,
        $eveUser->character_owner_hash
    );

    Event::fakeFor(fn () => $action($eveUser_changedRefreshToken));

    test()->assertDatabaseHas('refresh_tokens', [
        'character_id' => $eveUser->character_id,
        'refresh_token' => $eveUser_changedRefreshToken->refreshToken,
    ]);
});

it('updates a guest re-auth that widens the stored token scopes', function () {
    // Existing token for a character with no active session (guest re-auth), narrow scopes.
    $narrow = Event::fakeFor(fn () => RefreshToken::factory()->scopes(['esi-skills.read_skills.v1'])->create());
    $characterId = $narrow->character_id;

    expect($narrow->hasScope('esi-assets.read_assets.v1'))->toBeFalse();

    // Incoming re-auth grants a wider scope set.
    $wideScopes = ['esi-skills.read_skills.v1', 'esi-assets.read_assets.v1'];
    $incoming = RefreshToken::factory()->scopes($wideScopes)->make(['character_id' => $characterId]);

    $eveUser = new EveUser(
        character_id: $characterId,
        character_owner_hash: sha1((string) $characterId),
        token: $incoming->token,
        refreshToken: $incoming->refresh_token,
        expiresIn: 1200,
        user: ['scp' => $wideScopes],
    );

    $action = new UpdateRefreshTokenAction;
    Event::fakeFor(fn () => $action($eveUser)); // no actingAs → guest

    expect(RefreshToken::find($characterId)->hasScope('esi-assets.read_assets.v1'))->toBeTrue();
});

it('does not update a guest re-auth that does not widen scopes', function () {
    $scopes = ['esi-skills.read_skills.v1'];
    $existing = Event::fakeFor(fn () => RefreshToken::factory()->scopes($scopes)->create());
    $originalRefreshToken = $existing->refresh_token;

    // Same scopes, different refresh_token — must be preserved for a guest.
    $incoming = RefreshToken::factory()->scopes($scopes)->make(['character_id' => $existing->character_id]);

    $eveUser = new EveUser(
        character_id: $existing->character_id,
        character_owner_hash: sha1((string) $existing->character_id),
        token: $incoming->token,
        refreshToken: $incoming->refresh_token,
        expiresIn: 1200,
        user: ['scp' => $scopes],
    );

    $action = new UpdateRefreshTokenAction;
    Event::fakeFor(fn () => $action($eveUser)); // guest

    expect(RefreshToken::find($existing->character_id)->refresh_token)->toBe($originalRefreshToken);
});

it('does not update refresh token for new session of a valid refresh token user', function () {
    // create RefreshToken
    $eveUser = createEveUser();

    $action = new UpdateRefreshTokenAction;
    Event::fakeFor(fn () => $action($eveUser));

    test()->assertDatabaseHas('refresh_tokens', [
        'character_id' => $eveUser->character_id,
        'refresh_token' => $eveUser->refreshToken,
    ]);

    // Change RefreshToken

    $eveUser_changedRefreshToken = createEveUser(
        $eveUser->character_id,
        $eveUser->character_owner_hash
    );

    Event::fakeFor(fn () => $action($eveUser));

    test()->assertDatabaseMissing('refresh_tokens', [
        'character_id' => $eveUser->character_id,
        'refresh_token' => $eveUser_changedRefreshToken->refreshToken,
    ]);
});

test('restore trashed refresh token', function () {
    // create RefreshToken
    $eveUser = createEveUser();

    $action = new UpdateRefreshTokenAction;
    Event::fakeFor(fn () => $action($eveUser));

    test()->assertDatabaseHas('refresh_tokens', [
        'character_id' => $eveUser->character_id,
        'refresh_token' => $eveUser->refreshToken,
    ]);

    $refresh_token = RefreshToken::find($eveUser->character_id);

    // Assert if RefreshToken was created
    expect($refresh_token)
        ->character_id->toBe($eveUser->character_id)
        ->not()->toBeEmpty();

    // SoftDelete RefreshToken
    $refresh_token->delete();

    expect(RefreshToken::withoutTrashed()->firstWhere('character_id', $eveUser->character_id))->toBeNull();
    expect(RefreshToken::withTrashed()->firstWhere('character_id', $eveUser->character_id))
        ->not()->toBeNull()
        ->toBeInstanceOf(RefreshToken::class);

    // Recreate RefreshToken
    $eveUser_changedRefreshToken = createEveUser(
        $eveUser->character_id,
        $eveUser->character_owner_hash
    );

    Event::fakeFor(fn () => $action($eveUser_changedRefreshToken));

    expect(RefreshToken::find($eveUser->character_id))->not()->toBeEmpty();

    test()->assertDatabaseHas('refresh_tokens', [
        'character_id' => $eveUser->character_id,
        'refresh_token' => $eveUser_changedRefreshToken->refreshToken,
    ]);
});
