<?php

use Faker\Generator as Faker;
use Seatplus\Auth\Models\CharacterUser;
use Seatplus\Auth\Models\User;

$factory->define(User::class, function (Faker $faker) {
    return [
        'main_character'          => $faker->numberBetween(9000000, 98000000),
        'active'                  => true,
    ];
});

$factory->afterCreating(User::class, function ($user, $faker) {
    $user->characters()->save(factory(CharacterUser::class)->make());
});
