<?php

use Faker\Generator as Faker;
use Seatplus\Auth\Models\CharacterUser;
use Seatplus\Eveapi\Models\Character\CharacterInfo;

$factory->define(CharacterUser::class, function (Faker $faker) {
    return [
        'user_id'               => $faker->numberBetween(90000000, 98000000),
        'character_id'          => $faker->numberBetween(90000000, 98000000),
        'character_owner_hash'  => sha1($faker->text),
    ];
});

$factory->afterCreating(CharacterUser::class, function ($character_user, $faker) {
    $character_user->character()->associate(factory(CharacterInfo::class)->make())->toArray();
});
