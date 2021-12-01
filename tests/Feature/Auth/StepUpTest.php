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
use Laravel\Socialite\Contracts\Factory;
use Laravel\Socialite\Facades\Socialite;
use Seatplus\Eveapi\Models\Corporation\CorporationInfo;
use Seatplus\Eveapi\Models\RefreshToken;
use Seatplus\Eveapi\Models\SsoScopes;

beforeEach(function () {
    Event::fake();

    Socialite::shouldReceive('driver->scopes->redirect')->andReturn('');
});

test('one can request another scope', function () {

    // 1. Create refresh_token
    createRefreshTokenWithScopes(['a', 'b']);

    expect(test()->test_character->refresh_token->scopes)
        ->toBeArray()
        ->toBe(['a','b']);


    $add_scopes = implode(',', ['1', '2']);

    $response = test()->actingAs(test()->test_user)->get(route('auth.eve.step_up', [
        'character_id' => test()->test_character->character_id,
        'add_scopes'   => $add_scopes,
    ]));

    expect(session('step_up'))->toEqual(test()->test_character->character_id);
    expect(session('sso_scopes'))->toEqual(['a', 'b', '1', '2']);
});

test('one can request another scope for a deleted token', function () {

    // Delete the token
    $token = test()->test_character->refresh_token;
    $token->delete();

    expect(test()->test_character->refresh()->refresh_token)
        ->toBeNull();

    $add_scopes = implode(',', ['1', '2']);

    $response = test()->actingAs(test()->test_user)->get(route('auth.eve.step_up', [
        'character_id' => test()->test_character->character_id,
        'add_scopes'   => $add_scopes,
    ]));

    expect(session('step_up'))->toEqual(test()->test_character->character_id);
    expect(session('sso_scopes'))->toEqual(['1', '2']);
});



