<?php

use Faker\Generator as Faker;
use Seatplus\Eveapi\Models\Alliance\AllianceInfo;
use Seatplus\Eveapi\Models\Corporation\CorporationInfo;
use Seatplus\Eveapi\Models\SsoScopes;

$factory->define(SsoScopes::class, function (Faker $faker) {

    return [
        'selected_scopes' => collect()->toArray(),
        'morphable_id' => 1,
        'morphable_type' => $faker->randomElement([AllianceInfo::class, CorporationInfo::class])
    ];
});
