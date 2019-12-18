<?php

use Faker\Generator as Faker;
use Seatplus\Eveapi\Models\Alliance\AllianceInfo;
use Seatplus\Eveapi\Models\Character\CharacterAffiliation;
use Seatplus\Eveapi\Models\Character\CharacterInfo;
use Seatplus\Eveapi\Models\Corporation\CorporationInfo;

$factory->define(CharacterInfo::class, function (Faker $faker) {
    return [
        'character_id'    => $faker->numberBetween(9000000, 98000000),
        'name'            => $faker->name,
        'birthday'        => $faker->iso8601($max = 'now'),
        'gender'          => $faker->randomElement(['male', 'female']),
        'race_id'         => $faker->randomDigitNotNull,
        'bloodline_id'    => $faker->randomDigitNotNull,
    ];
});

$factory->afterCreating(CharacterInfo::class, function ($character_info, $faker) {
    $character_affiliation = $character_info->character_affiliation()->save(factory(CharacterAffiliation::class)->states('with_alliance')->create());

    $character_affiliation->corporation()->associate(factory(CorporationInfo::class)->create([
        'corporation_id' => $character_affiliation->corporation_id,
    ]));

    $character_affiliation->alliance()->associate(factory(AllianceInfo::class)->create([
        'alliance_id' => $character_affiliation->alliance_id,
    ]));
});
