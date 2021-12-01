<?php

/*
 * MIT License
 *
 * Copyright (c) 2019, 2020, 2021 Felix Huber
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

namespace Seatplus\Auth\Observers;

use Seatplus\Auth\Models\CharacterUser;
use Seatplus\Auth\Models\User;
use Seatplus\Eveapi\Models\Character\CharacterAffiliation;

class CharacterAffiliationObserver
{
    public function updated(CharacterAffiliation $affiliation)
    {
        if ($affiliation->corporation_id !== 1000001) {
            return;
        }

        $character_user = CharacterUser::firstWhere('character_id', $affiliation->character_id);

        // Check if more characters belong to the user
        // If solely character in user simply set active = false
        if ($character_user->user->characters->count() === 1) {
            $user = $character_user->user;
            $user->active = false;
            $user->save();

            return;
        }

        $is_main_character = $character_user->user->main_character_id === $affiliation->character_id;

        if ($is_main_character) {
            $new_main_character_id = $character_user->user->characters->pluck('character_id')->reject(fn ($id) => $id === $affiliation->character_id)->first();

            // update user
            User::where('main_character_id', $affiliation->character_id)->update(['main_character_id' => $new_main_character_id]);
        }

        // create a new user
        $new_user = User::create([
            'main_character_id' => $affiliation->character_id,
        ]);

        $new_user->active = false;
        $new_user->save();

        // update character_user to be affiliated to new user
        CharacterUser::where('character_id', $affiliation->character_id)->update(['user_id' => $new_user->id]);
    }
}
