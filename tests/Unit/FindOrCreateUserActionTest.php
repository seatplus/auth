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

use Seatplus\Auth\Http\Actions\Sso\FindOrCreateUserAction;
use Seatplus\Auth\Models\CharacterUser;

test('create new user', function () {
    $eve_user = createEveUser();

    test()->assertDatabaseMissing('users', [
        'main_character_id' => $eve_user->character_id,
    ]);

    $action = new FindOrCreateUserAction();
    $user = $action($eve_user);

    test()->assertDatabaseHas('users', [
        'main_character_id' => $eve_user->character_id,
    ]);

    test()->assertDatabaseHas('character_users', [
        'user_id' => $user->id,
        'character_id' => $eve_user->character_id,
    ]);
});

test('find existing user with two character', function () {
    // add 3 characters to test_user
    test()->test_user->character_users()->createMany(
        CharacterUser::factory()->count(3)->make()->toArray()
    );

    expect(test()->test_user->character_users->count())->toEqual(4);

    // select last character to login
    $secondary_character = test()->test_user->character_users->last();

    $eve_user = createEveUser(
        $secondary_character->character_id,
        $secondary_character->character_owner_hash
    );

    $action = new FindOrCreateUserAction();
    $user = $action($eve_user);

    expect($user->id)->toEqual(test()->test_user->id);

    test()->assertDatabaseHas('character_users', [
        'user_id' => test()->test_user->id,
        'character_id' => $secondary_character->character_id,
    ]);
});

test('deal with changed owner hash', function () {
    expect(1)->toEqual(test()->test_user->character_users->count());

    $eve_user = createEveUser(
        test()->test_user->character_users->first()->character_id,
        'anotherHashValue'
    );

    $action = new FindOrCreateUserAction();
    $user = $action($eve_user);

    test()->assertDatabaseHas('users', [
        'id' => $user->id,
    ]);

    test()->assertDatabaseHas('users', [
        'id' => test()->test_user->id,
    ]);

    expect(test()->test_user->id)->not()->toBe($user->id);

    test()->assertDatabaseMissing('character_users', [
        'user_id' => test()->test_user->id,
        'character_id' => $user->id,
    ]);
});

test('deal with two characters with one changed owner hash', function () {
    // 1. Create secondary character
    $secondary_user = CharacterUser::factory()->make();

    // 2. assign secondary user to test_user
    test()->test_user->character_users()->save($secondary_user);

    expect(test()->test_user->character_users->count())->toEqual(2);

    // 3. find user

    $eve_user = createEveUser(
        $secondary_user->character_id,
        'anotherHashValue'
    );

    $action = new FindOrCreateUserAction();
    $user = $action($eve_user);

    expect($user->character_users->count())->toEqual(1);

    expect(CharacterUser::all()->count())->toEqual(2);

    test()->assertDatabaseHas('users', [
        'id' => test()->test_user->id,
    ]);

    test()->assertDatabaseHas('users', [
        'id' => $user->id,
    ]);

    expect(test()->test_user->id)->not()->toBe($user->id);

    //5. assert that secondary character is not affiliated to first user

    test()->assertDatabaseMissing('character_users', [
        'user_id' => test()->test_user->id,
        'character_id' => $secondary_user->character_id,
    ]);

    test()->assertDatabaseHas('character_users', [
        'user_id' => $user->id,
        'character_id' => $secondary_user->character_id,
    ]);
});

it('returns authed user', function () {
    // 1. Create secondary character
    $secondary_user = CharacterUser::factory()->make();

    $eve_user = createEveUser(
        $secondary_user->character_id,
        $secondary_user->character_owner_hash
    );

    $action = new FindOrCreateUserAction();

    // act as test user
    test()->actingAs(test()->test_user);

    $user = $action($eve_user);

    // Assert that test user id and the returned user id is equal
    expect($user->id)->toEqual(test()->test_user->id);

    // assert that character user relation has been set
    test()->assertDatabaseHas('character_users', [
        'user_id' => test()->test_user->id,
        'character_id' => $secondary_user->character_id,
    ]);

    expect(test()->test_user->character_users->count())->toEqual(2);
});
