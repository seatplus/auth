<?php

namespace Seatplus\Auth\Observers;

use Seatplus\Auth\Models\CharacterUser;
use Seatplus\Auth\Models\User;
use Seatplus\Eveapi\Models\Character\CharacterAffiliation;

class CharacterAffiliationObserver
{

    public function updated(CharacterAffiliation $affiliation)
    {

        if($affiliation->corporation_id !== 1000001)
            return;

        $character_user = CharacterUser::firstWhere('character_id', $affiliation->character_id);

        // Check if more characters belong to the user
        // If solely character in user simply set active = false
        if($character_user->user->characters->count() === 1) {
            $user = $character_user->user;
            $user->active = false;
            $user->save();

            return;
        }

        $is_main_character = $character_user->user->main_character_id === $affiliation->character_id;

        if($is_main_character) {
            $new_main_character_id = $character_user->user->characters->pluck('character_id')->reject(fn($id) => $id === $affiliation->character_id)->first();

            // update user
            User::where('main_character_id', $affiliation->character_id)->update(['main_character_id' => $new_main_character_id]);
        }

        // create a new user
        $new_user = User::create([
            'main_character_id' => $affiliation->character_id
        ]);

        $new_user->active = false;
        $new_user->save();

        // update character_user to be affiliated to new user
        CharacterUser::where('character_id', $affiliation->character_id)->update(['user_id' => $new_user->id]);
    }

}