<?php

/*
 * MIT License
 *
 * Copyright (c) 2019, 2020 Felix Huber
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

use Faker\Generator as Faker;
use Seatplus\Eveapi\Models\Character\CharacterAffiliation;
use Seatplus\Eveapi\Models\Character\CharacterInfo;

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

$factory->state(CharacterAffiliation::class, 'with_character', function (Faker $faker) {
    return [
        'character_id' => factory(CharacterInfo::class),
    ];
});
