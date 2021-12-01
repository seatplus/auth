<?php

it('deactivates user if character is doomsheimed', function () {

    expect($this->test_user)
        ->active->toBeTrue()
        ->characters->toHaveCount(1);

    $character_affiliation = \Seatplus\Eveapi\Models\Character\CharacterAffiliation::firstWhere('character_id', $this->test_user->characters->first()->character_id);

    // doomheim the character
    $character_affiliation->corporation_id = 1000001;
    $character_affiliation->save();

    expect($this->test_user->refresh())
        ->active->toBeFalsy();

});

it('splits secondary user to a new user if doomsheimed', function () {

    $user = test()->test_user;
    $user->main_character_id = test()->test_character->character_id;
    $user->save();

    $character_user = \Seatplus\Auth\Models\CharacterUser::factory()
        ->create(['user_id' => test()->test_user->id]);

    expect(test()->test_user->refresh())
        ->active->toBeTruthy()
        ->characters->toHaveCount(2)
        ->main_character_id->toBeInt()->toBe(test()->test_character->character_id)
        ->main_character_id->not()->toBe($character_user->character_id);

    // doomheim the character
    $character_affiliation = \Seatplus\Eveapi\Models\Character\CharacterAffiliation::firstWhere('character_id',$character_user->character_id);
    $character_affiliation->corporation_id = 1000001;
    $character_affiliation->save();

    // original user should still be active
    expect($this->test_user->refresh())
        ->active->toBeTruthy()
        ->main_character_id->toBeInt()->toBe(test()->test_character->character_id)
        ->characters->toHaveCount(1);

});


it('splits primary user to a new user if doomsheimed', function () {

    $user = test()->test_user;
    $user->main_character_id = test()->test_character->character_id;
    $user->save();

    $character_user = \Seatplus\Auth\Models\CharacterUser::factory()
        ->create(['user_id' => test()->test_user->id]);

    expect(test()->test_user->refresh())
        ->active->toBeTruthy()
        ->characters->toHaveCount(2)
        ->main_character_id->toBeInt()->toBe(test()->test_character->character_id)
        ->main_character_id->not()->toBe($character_user->character_id);

    expect(\Seatplus\Auth\Models\User::all())->toHaveCount(1);

    // doomheim the character
    $character_affiliation = \Seatplus\Eveapi\Models\Character\CharacterAffiliation::firstWhere('character_id',test()->test_character->character_id);
    $character_affiliation->corporation_id = 1000001;
    $character_affiliation->save();

    expect(\Seatplus\Auth\Models\User::all())->toHaveCount(2);

    // original user should still be active
    expect($user->refresh())
        ->active->toBeTruthy()
        ->main_character_id->toBeInt()->not()->toBe(test()->test_character->character_id)
        ->main_character_id->toBeInt()->toBe($character_user->character_id)
        ->characters->toHaveCount(1);

    expect(\Seatplus\Auth\Models\User::firstWhere('main_character_id', '<>', $character_user->character_id))
        ->active->toBeFalsy()
        ->main_character_id->toBeInt()->toBe(test()->test_character->character_id)
        ->main_character_id->toBeInt()->not()->toBe($character_user->character_id)
        ->characters->toHaveCount(1);

});