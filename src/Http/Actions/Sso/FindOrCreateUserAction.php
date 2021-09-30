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

namespace Seatplus\Auth\Http\Actions\Sso;

use Seatplus\Auth\Containers\EveUser;
use Seatplus\Auth\Models\CharacterUser;
use Seatplus\Auth\Models\User;

class FindOrCreateUserAction
{
    private EveUser $eve_user;

    private ?CharacterUser $character_user;

    private User $user;

    public function __invoke(EveUser $eve_user): User
    {
        $this->eve_user = $eve_user;
        $this->character_user = CharacterUser::firstWhere('character_id', $eve_user->character_id);

        $this->setUserFromUnchangedOwnerHash();
        $this->handleChangedOwnerHash();

        $user = $this->getUser();

        $this->handleCharacterUserEntry($user, $eve_user);

        return $user;
    }

    private function handleCharacterUserEntry(User $user, EveUser $eve_user)
    {
        // When character_user is set and found skip
        if ($this->character_user) {
            return;
        }

        CharacterUser::firstOrCreate([
            'user_id'              => $user->id,
            'character_id'         => $eve_user->character_id,
        ], [
            'character_owner_hash' => $eve_user->character_owner_hash,
        ]);
    }

    private function getUser(): User
    {
        if (! isset($this->user)) {
            $this->user = auth()->user() ?? User::create([
                'main_character_id' => $this->eve_user->character_id,
                'active'            => true,
            ]);
        }

        return $this->user;
    }

    private function handleChangedOwnerHash()
    {
        // If character_user is unknown or character_owner_hash did not change don't bother anymore
        if (empty($this->character_user) || ($this->character_user->character_owner_hash === $this->eve_user->character_owner_hash)) {
            return;
        }

        /*
         * If user is known and character_owner_hash changed it means that the
         * character might have been transferred. We create a new user and
         * return the new user
         */

        // First let's check if this is the only character within the user group
        if ($this->character_user->user->characters->count() < 2) {
            // reset main_character name as this single user account went stale
            $this->character_user->user->main_character_id = null;
            $this->character_user->user->save();
        }

        // Delete character_user model
        CharacterUser::where('character_id', $this->eve_user->character_id)->delete();
        // reset found character_user
        $this->character_user = null;
    }

    private function setUserFromUnchangedOwnerHash()
    {
        /*
         * If user is known and character_owner_hash didn't change return the user. This might cause an exploit
         *  if the character is shared with other users, which is not allowed according to CCP.
        */
        if (! empty($this->character_user) && $this->character_user->character_owner_hash === $this->eve_user->character_owner_hash) {
            $this->user = $this->character_user->user;
        }
    }
}
