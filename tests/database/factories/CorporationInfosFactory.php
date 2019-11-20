<?php

use Faker\Generator as Faker;
use Seatplus\Eveapi\Models\Corporation\CorporationInfo;

$factory->define(CorporationInfo::class, function (Faker $faker) {
    return [
        'corporation_id'  => $faker->numberBetween(98000000, 99000000),
        'ticker'          => $faker->bothify('[##??]'),
        'name'            => $faker->name,
        'member_count'    => $faker->randomDigitNotNull,
        'ceo_id'          => $faker->numberBetween(90000000, 98000000),
        'creator_id'      => $faker->numberBetween(90000000, 98000000),
        'tax_rate'        => $faker->randomFloat(2, 0, 1),
        'alliance_id'     => $faker->optional()->numberBetween(99000000, 100000000),
    ];
});
