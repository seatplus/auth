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
use Seatplus\Auth\Models\User;
use Seatplus\Auth\Tests\TestCase;
use Seatplus\Eveapi\Models\Character\CharacterInfo;


beforeEach(function () {
    parent::setUp(); // TODO: Change the autogenerated stub

    Event::fake();
});

it('has main character relationship', function () {
    $test_user = User::factory()->create();

    expect($test_user->main_character)->toBeInstanceOf(CharacterInfo::class);
});

it('has characters relationship', function () {
    $test_user = User::factory()->create();

    test()->assertDatabaseHas('character_users', [
        'character_id' => $test_user->character_users->first()->character_id,
    ]);

    test()->assertDatabaseHas('character_infos', [
        'character_id' => $test_user->character_users->first()->character_id,
    ]);

    expect($test_user->characters->first())->toBeInstanceOf(CharacterInfo::class);
});

it('has search scope', function () {
    $test_user = User::factory()->create();

    $character = $test_user->characters->first();

    $user = User::search($character->name)->first();

    expect($user->id)->toEqual($test_user->id);
});
