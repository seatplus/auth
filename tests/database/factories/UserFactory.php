<?php

use Faker\Generator as Faker;
use Seatplus\Auth\Models\CharacterUser;
use Seatplus\Auth\Models\User;
use Seatplus\Eveapi\Models\Character\CharacterInfo;

$factory->define(User::class, function (Faker $faker) {
    return [
        'main_character_id'       => factory(CharacterInfo::class),
        'active'                  => true,
    ];
});

$factory->afterCreating(User::class, function ($user, $faker) {
    $user->characters()->save(factory(CharacterUser::class)->make());
});
