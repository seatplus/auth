<?php

use Faker\Generator as Faker;
use Seatplus\Eveapi\Models\Character\CharacterAffiliation;

$factory->define(CharacterAffiliation::class, function (Faker $faker) {
    return [
        'character_id'    => $faker->numberBetween(9000000, 98000000),
        'corporation_id'  => $faker->numberBetween(98000000, 99000000),
        'alliance_id'     => $faker->optional()->numberBetween(99000000, 100000000),
        'faction_id'      => $faker->optional()->numberBetween(500000, 1000000),
        'last_pulled'     => $faker->dateTime(),
    ];
});

$factory->state(CharacterAffiliation::class, 'with_alliance', function (Faker $faker) {
    return [
        'alliance_id'     => $faker->unique()->numberBetween(99000000, 100000000),
    ];
});
