<?php

use Faker\Generator as Faker;
use Seatplus\Eveapi\Models\Alliance\AllianceInfo;


$factory->define(AllianceInfo::class, function (Faker $faker) {

    return [
        'alliance_id' => $faker->optional()->numberBetween(99000000,100000000),
        'creator_corporation_id'  => $faker->numberBetween(98000000, 99000000),
        'creator_id' => $faker->numberBetween(90000000, 98000000),
        'date_founded' => $faker->iso8601($max = 'now'),
        'executor_corporation_id'  => $faker->optional()->numberBetween(98000000, 99000000),
        'name'            => $faker->name,
        'ticker' => $faker->bothify('[##??]'),
        'faction_id' => $faker->optional()->numberBetween(500000, 1000000)
    ];
});
