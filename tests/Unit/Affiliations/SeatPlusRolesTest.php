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

use Illuminate\Support\Facades\Event;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Eveapi\Models\Alliance\AllianceInfo;
use Seatplus\Eveapi\Models\Character\CharacterInfo;
use Seatplus\Eveapi\Models\Corporation\CorporationInfo;

//uses(\Seatplus\Auth\Tests\TestCase::class);

beforeEach(function () {
    Event::fake();

    test()->secondary_character = CharacterInfo::factory()->create();

    test()->tertiary_character = CharacterInfo::factory()->create();

    test()->role = Role::create(['name' => 'derp']);
});

test('user has no roles test', function () {
    expect(test()->test_user->roles->isEmpty())->toBeTrue();
});

test('user has role test', function () {
    test()->test_user->assignRole(test()->role);

    expect(test()->test_user->roles->isNotEmpty())->toBeTrue();
});

test('role has no affiliation test', function () {
    expect(test()->role->affiliations->isEmpty())->toBeTrue();
});

test('role has an affiliation test', function () {
    test()->role->affiliations()->create([
        'affiliatable_id' => test()->test_character->character_id,
        'affiliatable_type' => CharacterInfo::class,
        'type' => 'allowed',
    ]);

    test()->assertNotNUll(test()->role->affiliations);
});

test('user is in affiliation test', function () {
    test()->role->affiliations()->create([
        'affiliatable_id' => test()->test_character->character_id,
        'affiliatable_type' => CharacterInfo::class,
        'type' => 'allowed',
    ]);

    expect(in_array(test()->test_character->character_id, test()->role->affiliated_ids))->toBeTrue();
});

test('character is in character allowed affiliation test', function () {
    $secondary_character = CharacterInfo::factory()->create();

    test()->role->affiliations()->createMany([
        [
            'affiliatable_id' => test()->test_character->character_id,
            'affiliatable_type' => CharacterInfo::class,
            'type' => 'allowed',
        ],
        [
            'affiliatable_id' => $secondary_character->character_id,
            'affiliatable_type' => CharacterInfo::class,
            'type' => 'allowed',
        ],

    ]);

    expect(in_array(test()->test_character->character_id, test()->role->affiliated_ids))->toBeTrue();
    expect(in_array($secondary_character->character_id, test()->role->affiliated_ids))->toBeTrue();
});

test('character is in character inversed affiliation test', function () {
    test()->role->affiliations()->createMany([
        [
            'affiliatable_id' => test()->test_character->character_id,
            'affiliatable_type' => CharacterInfo::class,
            'type' => 'inverse',
        ],
        [
            'affiliatable_id' => 1234,
            'affiliatable_type' => CharacterInfo::class,
            'type' => 'inverse',
        ],
    ]);

    expect(in_array(test()->test_character->character_id, test()->role->affiliated_ids))->toBeFalse();
    expect(in_array(test()->secondary_character->character_id, test()->role->affiliated_ids))->toBeTrue();
});

test('character is not in character inverse affiliation test', function () {
    test()->role->affiliations()->createMany([
        [
            'affiliatable_id' => test()->secondary_character->character_id,
            'affiliatable_type' => CharacterInfo::class,
            'type' => 'inverse',
        ],
        [
            'affiliatable_id' => test()->tertiary_character->character_id,
            'affiliatable_type' => CharacterInfo::class,
            'type' => 'inverse',
        ],
    ]);

    expect(in_array(test()->test_character->character_id, test()->role->affiliated_ids))->toBeTrue();
    expect(in_array(test()->secondary_character->character_id, test()->role->affiliated_ids))->toBeFalse();
    expect(in_array(test()->tertiary_character->character_id, test()->role->affiliated_ids))->toBeFalse();
});

test('character is in character forbidden affiliation test', function () {
    test()->role->affiliations()->createMany([
        [
            'affiliatable_id' => test()->test_character->character_id,
            'affiliatable_type' => CharacterInfo::class,
            'type' => 'forbidden',
        ],
        [
            'affiliatable_id' => test()->secondary_character->character_id,
            'affiliatable_type' => CharacterInfo::class,
            'type' => 'forbidden',
        ],
    ]);

    expect(in_array(test()->test_character->character_id, test()->role->affiliated_ids))->toBeFalse();
    expect(in_array(test()->secondary_character->character_id, test()->role->affiliated_ids))->toBeFalse();
    expect(in_array(test()->tertiary_character->character_id, test()->role->affiliated_ids))->toBeFalse();
});

test('character is in corporation allowed affiliation test', function () {
    test()->role->affiliations()->create([
        'affiliatable_id' => test()->test_character->corporation_id,
        'affiliatable_type' => CorporationInfo::class,
        'type' => 'allowed',
    ]);

    expect(in_array(test()->test_character->character_id, test()->role->affiliated_ids))->toBeTrue();
    expect(in_array(test()->secondary_character->character_id, test()->role->affiliated_ids))->toBeFalse();
    expect(in_array(test()->tertiary_character->character_id, test()->role->affiliated_ids))->toBeFalse();
});

test('character is in corporation inversed affiliation test', function () {
    test()->role->affiliations()->createMany([
        [
            'affiliatable_id' => test()->test_character->corporation_id,
            'affiliatable_type' => CorporationInfo::class,
            'type' => 'inverse',
        ],
        [
            'affiliatable_id' => test()->secondary_character->corporation_id,
            'affiliatable_type' => CorporationInfo::class,
            'type' => 'inverse',
        ],
    ]);

    expect(in_array(test()->test_character->character_id, test()->role->affiliated_ids))->toBeFalse();
    expect(in_array(test()->secondary_character->character_id, test()->role->affiliated_ids))->toBeFalse();
    expect(in_array(test()->tertiary_character->character_id, test()->role->affiliated_ids))->toBeTrue();
});

test('character is in corporation forbidden affiliation test', function () {
    test()->role->affiliations()->createMany([
        [
            'affiliatable_id' => test()->test_character->corporation_id,
            'affiliatable_type' => CorporationInfo::class,
            'type' => 'forbidden',
        ],
        [
            'affiliatable_id' => test()->secondary_character->corporation_id,
            'affiliatable_type' => CorporationInfo::class,
            'type' => 'forbidden',
        ],
    ]);

    expect(in_array(test()->test_character->character_id, test()->role->affiliated_ids))->toBeFalse();
    expect(in_array(test()->secondary_character->character_id, test()->role->affiliated_ids))->toBeFalse();
    expect(in_array(test()->tertiary_character->character_id, test()->role->affiliated_ids))->toBeFalse();
});

test('character is in alliance allowed affiliation test', function () {
    test()->role->affiliations()->createMany([
        [
            'affiliatable_id' => test()->test_character->alliance_id,
            'affiliatable_type' => AllianceInfo::class,
            'type' => 'allowed',
        ],
        [
            'affiliatable_id' => test()->secondary_character->alliance_id,
            'affiliatable_type' => AllianceInfo::class,
            'type' => 'allowed',
        ],
    ]);

    expect(in_array(test()->test_character->character_id, test()->role->affiliated_ids))->toBeTrue();
    expect(in_array(test()->secondary_character->character_id, test()->role->affiliated_ids))->toBeTrue();
    expect(in_array(test()->tertiary_character->character_id, test()->role->affiliated_ids))->toBeFalse();
});

test('character is in alliance inversed affiliation test', function () {
    test()->role->affiliations()->createMany([
        [
            'affiliatable_id' => test()->test_character->alliance_id,
            'affiliatable_type' => AllianceInfo::class,
            'type' => 'inverse',
        ],
        [
            'affiliatable_id' => test()->secondary_character->alliance_id,
            'affiliatable_type' => AllianceInfo::class,
            'type' => 'inverse',
        ],
    ]);

    expect(in_array(test()->test_character->character_id, test()->role->affiliated_ids))->toBeFalse();
    expect(in_array(test()->secondary_character->character_id, test()->role->affiliated_ids))->toBeFalse();
    expect(in_array(test()->tertiary_character->character_id, test()->role->affiliated_ids))->toBeTrue();
});

test('character is in alliance forbidden affiliation test', function () {
    test()->role->affiliations()->createMany([
        [
            'affiliatable_id' => test()->test_character->alliance_id,
            'affiliatable_type' => AllianceInfo::class,
            'type' => 'forbidden',
        ],
        [
            'affiliatable_id' => test()->secondary_character->alliance_id,
            'affiliatable_type' => AllianceInfo::class,
            'type' => 'forbidden',
        ],
    ]);

    expect(in_array(test()->test_character->character_id, test()->role->affiliated_ids))->toBeFalse();
    expect(in_array(test()->secondary_character->character_id, test()->role->affiliated_ids))->toBeFalse();
    expect(in_array(test()->tertiary_character->character_id, test()->role->affiliated_ids))->toBeFalse();
});
