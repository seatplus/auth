<?php

namespace Seatplus\Auth\Http\Actions\Sso;

use Laravel\Socialite\Two\User as EveUser;
use Seatplus\Auth\Models\CharacterUser;
use Seatplus\Auth\Models\User;

class FindOrCreateUserAction
{
    /**
     * @var \Laravel\Socialite\Two\User
     */
    private $eve_user;

    private $character_user;

    public function execute(EveUser $eve_user): User
    {
        $this->eve_user = $eve_user;

        $this->character_user = CharacterUser::where('character_id', $eve_user->character_id)->first();

        // If user is known and character_owner_hash didn't change return the user
        if (!empty($this->character_user) && $this->character_user->character_owner_hash === $eve_user->character_owner_hash) {
            return $this->character_user->user;
        }

        /*
         * If user is known and character_owner_hash changed it means that the
         * character might have been transferred. We create a new user and
         * return the new user
         */
        if (!empty($this->character_user) && $this->character_user->character_owner_hash !== $this->eve_user->character_owner_hash) {
            $this->handleChangedOwnerHash();
        }

        $user = User::create([
            'main_character_id' => $eve_user->character_id,
            'active'            => true,
        ]);

        $this->createCharacterUserEntry($user->id, $eve_user);

        return $user;
    }

    private function createCharacterUserEntry(int $user_id, EveUser $eve_user)
    {
        CharacterUser::create([
            'user_id'              => $user_id,
            'character_id'         => $eve_user->character_id,
            'character_owner_hash' => $eve_user->character_owner_hash,
        ]);
    }

    private function handleChangedOwnerHash()
    {
        // First let's check if this is the only character within the user group
        if ($this->character_user->user->characters->count() < 2) {
            // reset main_character name as this single user account went stale
            $this->character_user->user->main_character_id = null;
            $this->character_user->user->save();
        }

        // Delete character_user relationship
        CharacterUser::where('character_id', $this->eve_user->character_id)->delete();
    }
}
