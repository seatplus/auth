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
use Seatplus\Auth\Actions\GetAffiliatedIdsByPermissionArray;
use Seatplus\Auth\Models\Permissions\Permission;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Tests\TestCase;
use Seatplus\Eveapi\Models\Alliance\AllianceInfo;
use Seatplus\Eveapi\Models\Character\CharacterAffiliation;
use Seatplus\Eveapi\Models\Character\CharacterInfo;
use Seatplus\Eveapi\Models\Character\CharacterRole;
use Seatplus\Eveapi\Models\Corporation\CorporationInfo;
use Seatplus\Eveapi\Tests\Unit\Services\Jobs\Alliance\AllianceInfoActionTest;

uses(TestCase::class);

beforeEach(function () {
    test()->role = Role::create(['name' => 'writer']);
    test()->permission = Permission::create(['name' => 'edit articles']);

    test()->role->givePermissionTo(test()->permission);
    test()->test_user->assignRole(test()->role);

    test()->test_character_user = test()->test_user->character_users->first();

    test()->actingAs(test()->test_user);

    Event::fakeFor(function () {

        test()->secondary_character = CharacterInfo::factory()->create();

        test()->tertiary_character = CharacterInfo::factory()->create();
    });

});

/**
 *
 * @throws \Exception
 */
it('returns own character id', function () {
   test()->role->affiliations()->create([
        'affiliatable_id' => test()->test_character->character_id,
        'affiliatable_type' => CharacterInfo::class,
        'type'         => 'allowed',
    ]);

    $ids = (new GetAffiliatedIdsByPermissionArray(test()->permission->name))->execute();

    expect(in_array(test()->test_character_user->character_id, $ids))->toBeTrue();
});

it('returns other and own character id for inverted', function () {
    test()->role->affiliations()->create([
        'affiliatable_id' => test()->test_character->character_id,
        'affiliatable_type' => CharacterInfo::class,
        'type'         => 'inverse',
    ]);

    $ids = (new GetAffiliatedIdsByPermissionArray(test()->permission->name))->execute();

    expect(in_array(test()->test_character_user->character_id, $ids))->toBeTrue();
    expect(in_array(test()->secondary_character->character_id, $ids))->toBeTrue();
});

it('does not return secondary character id if secondary character is inverted', function () {
    test()->role->affiliations()->create([
        'affiliatable_id' => test()->secondary_character->character_id,
        'affiliatable_type' => CharacterInfo::class,
        'type'         => 'inverse',
    ]);

    $ids = (new GetAffiliatedIdsByPermissionArray(test()->permission->name))->execute();

    // Assert that the owned character_ids are present
    expect(in_array(test()->test_character_user->character_id, $ids))->toBeTrue();

    // Assert that ids from the inverted corporation is missing
    expect(in_array(test()->secondary_character->character_id, $ids))->toBeFalse();

    // Assert that ids from any other third party is present
    expect(in_array(test()->tertiary_character->character_id, $ids))->toBeTrue();
});

it('does not return secondary character id if secondary corporation is inverted', function () {
    test()->role->affiliations()->create([
        'affiliatable_id' => test()->secondary_character->corporation->corporation_id,
        'affiliatable_type' => CorporationInfo::class,
        'type'           => 'inverse',
    ]);

    $ids = (new GetAffiliatedIdsByPermissionArray(test()->permission->name))->execute();

    // Assert that second character does not share same corporation as the first character
    test()->assertNotEquals(test()->secondary_character->corporation->corporation_id, test()->test_character->corporation->corporation_id);

    // Assert that the owned character_ids are present
    expect(in_array(test()->test_character_user->character_id, $ids))->toBeTrue();

    // Assert that ids from the inverted corporation is missing
    expect(in_array(test()->secondary_character->character_id, $ids))->toBeFalse();

    // Assert that ids from any other third party is present
    expect(in_array(test()->tertiary_character->character_id, $ids))->toBeTrue();
});

it('does not return secondary character id if secondary alliance is inverted', function () {
    test()->role->affiliations()->create([
        'affiliatable_id' => test()->secondary_character->alliance->alliance_id,
        'affiliatable_type' => AllianceInfo::class,
        'type'        => 'inverse',
    ]);

    $ids = (new GetAffiliatedIdsByPermissionArray(test()->permission->name))->execute();

    // Assert that second character does not share same corporation as the first character
    test()->assertNotEquals(test()->secondary_character->alliance->alliance_id, test()->test_character->alliance->alliance_id);

    // Assert that the owned character_ids are present
    expect(in_array(test()->test_character_user->character_id, $ids))->toBeTrue();

    // Assert that ids from the inverted corporation is missing
    expect(in_array(test()->secondary_character->character_id, $ids))->toBeFalse();

    // Assert that ids from any other third party is present
    expect(in_array(test()->tertiary_character->character_id, $ids))->toBeTrue();
});

it('does return secondary character id if secondary character is allowed', function () {
    test()->role->affiliations()->create([
        'affiliatable_id' => test()->secondary_character->character_id,
        'affiliatable_type' => CharacterInfo::class,
        'type'         => 'allowed',
    ]);

    $ids = (new GetAffiliatedIdsByPermissionArray(test()->permission->name))->execute();

    // Assert that second character does not share same corporation as the first character
    test()->assertNotEquals(test()->secondary_character->character_id, test()->test_character->character_id);

    // Assert that the owned character_ids are present
    expect(in_array(test()->test_character_user->character_id, $ids))->toBeTrue();

    // Assert that ids from the allowed character is present
    expect(in_array(test()->secondary_character->character_id, $ids))->toBeTrue();

    // Assert that ids from any other third party is not present
    expect(in_array(test()->tertiary_character->character_id, $ids))->toBeFalse();
});

it('does return secondary character id if secondary corporation is allowed', function () {
    test()->role->affiliations()->create([
        'affiliatable_id' => test()->secondary_character->corporation->corporation_id,
        'affiliatable_type' => CorporationInfo::class,
        'type'           => 'allowed',
    ]);

    $ids = (new GetAffiliatedIdsByPermissionArray(test()->permission->name))->execute();

    // Assert that second character does not share same corporation as the first character
    test()->assertNotEquals(test()->secondary_character->corporation->corporation_id, test()->test_character->corporation->corporation_id);

    // Assert that the owned character_ids are present
    expect(in_array(test()->test_character_user->character_id, $ids))->toBeTrue();

    // Assert that ids from the allowed character is present
    expect(in_array(test()->secondary_character->character_id, $ids))->toBeTrue();

    // Assert that ids from any other third party is not present
    expect(in_array(test()->tertiary_character->character_id, $ids))->toBeFalse();
});

it('does return secondary character id if secondary alliance is allowed', function () {
    test()->role->affiliations()->create([
        'affiliatable_id' => test()->secondary_character->alliance->alliance_id,
        'affiliatable_type' => AllianceInfo::class,
        'type'        => 'allowed',
    ]);

    $ids = (new GetAffiliatedIdsByPermissionArray(test()->permission->name))->execute();

    // Assert that second character does not share same corporation as the first character
    test()->assertNotEquals(test()->secondary_character->alliance->alliance_id, test()->test_character->alliance->alliance_id);

    // Assert that the owned character_ids are present
    expect(in_array(test()->test_character_user->character_id, $ids))->toBeTrue();

    // Assert that ids from the allowed character is present
    expect(in_array(test()->secondary_character->character_id, $ids))->toBeTrue();

    // Assert that ids from any other third party is not present
    expect(in_array(test()->tertiary_character->character_id, $ids))->toBeFalse();
});

it('does return own character even if listed as forbidden', function () {
    test()->role->affiliations()->create([
        'affiliatable_id' => test()->secondary_character->character_id,
        'affiliatable_type' => CharacterInfo::class,
        'type'         => 'forbidden',
    ]);

    $ids = (new GetAffiliatedIdsByPermissionArray(test()->permission->name))->execute();

    // Assert that the owned character_ids are present
    expect(in_array(test()->test_character_user->character_id, $ids))->toBeTrue();
});

it('does not return secondary character id if secondary character is forbidden', function () {
    test()->role->affiliations()->createMany([
        [
            'affiliatable_id' => test()->secondary_character->character_id,
            'affiliatable_type' => CharacterInfo::class,
            'type'         => 'allowed',
        ],
        [
            'affiliatable_id' => test()->secondary_character->character_id,
            'affiliatable_type' => CharacterInfo::class,
            'type'         => 'forbidden',
        ],
    ]);

    $ids = (new GetAffiliatedIdsByPermissionArray(test()->permission->name))->execute();

    // Assert that second character does not share same corporation as the first character
    test()->assertNotEquals(test()->secondary_character->character_id, test()->test_character->character_id);

    // Assert that the owned character_ids are present
    expect(in_array(test()->test_character_user->character_id, $ids))->toBeTrue();

    // Assert that ids from the allowed character is not present
    expect(in_array(test()->secondary_character->character_id, $ids))->toBeFalse();

    // Assert that ids from any other third party is not present
    expect(in_array(test()->tertiary_character->character_id, $ids))->toBeFalse();
});

it('does not return secondary character id if secondary corporation is forbidden', function () {
    test()->role->affiliations()->createMany([
        [
            'affiliatable_id' => test()->secondary_character->corporation->corporation_id,
            'affiliatable_type' => CorporationInfo::class,
            'type'           => 'allowed',
        ],
        [
            'affiliatable_id' => test()->secondary_character->corporation->corporation_id,
            'affiliatable_type' => CorporationInfo::class,
            'type'           => 'forbidden',
        ],
    ]);

    $ids = (new GetAffiliatedIdsByPermissionArray(test()->permission->name))->execute();

    // Assert that second character does not share same corporation as the first character
    test()->assertNotEquals(test()->secondary_character->corporation->corporation_id, test()->test_character->corporation->corporation_id);

    // Assert that the owned character_ids are present
    expect(in_array(test()->test_character_user->character_id, $ids))->toBeTrue();

    // Assert that ids from the allowed character is not present
    expect(in_array(test()->secondary_character->character_id, $ids))->toBeFalse();

    // Assert that ids from any other third party is not present
    expect(in_array(test()->tertiary_character->character_id, $ids))->toBeFalse();
});

it('does not return secondary character id if secondary alliance is forbidden', function () {
    test()->role->affiliations()->createMany([
        [
            'affiliatable_id' => test()->secondary_character->alliance->alliance_id,
            'affiliatable_type' => AllianceInfo::class,
            'type'        => 'allowed',
        ],
        [
            'affiliatable_id' => test()->secondary_character->alliance->alliance_id,
            'affiliatable_type' => AllianceInfo::class,
            'type'        => 'forbidden',
        ],
    ]);

    $ids = (new GetAffiliatedIdsByPermissionArray(test()->permission->name))->execute();

    // Assert that second character does not share same corporation as the first character
    test()->assertNotEquals(test()->secondary_character->alliance->alliance_id, test()->test_character->alliance->alliance_id);

    // Assert that the owned character_ids are present
    expect(in_array(test()->test_character_user->character_id, $ids))->toBeTrue();

    // Assert that ids from the allowed character is not present
    expect(in_array(test()->secondary_character->character_id, $ids))->toBeFalse();

    // Assert that ids from any other third party is not present
    expect(in_array(test()->tertiary_character->character_id, $ids))->toBeFalse();
});

it('caches results', function () {
    test()->role->affiliations()->createMany([
        [
            'affiliatable_id' => test()->secondary_character->alliance->alliance_id,
            'affiliatable_type' => AllianceInfo::class,
            'type'        => 'allowed',
        ],
        [
            'affiliatable_id' => test()->secondary_character->alliance->alliance_id,
            'affiliatable_type' => AllianceInfo::class,
            'type'        => 'forbidden',
        ],
    ]);

    $action = new GetAffiliatedIdsByPermissionArray(test()->permission->name);

    expect(cache()->has($action->getCacheKey()))->toBeFalse();

    $ids = $action->execute();

    expect(cache()->has($action->getCacheKey()))->toBeTrue();
    expect(cache($action->getCacheKey()))->toEqual($ids);
});

it('returns corporation id', function () {
    // first make sure test_character corporation is in the alliance
    $corporation = test()->test_character->corporation;
    $corporation->alliance_id = test()->test_character->alliance->alliance_id;
    $corporation->save();

    // create role affiliation on alliance level
    test()->role->affiliations()->create([
        'affiliatable_id' => test()->test_character->alliance->alliance_id,
        'affiliatable_type' => AllianceInfo::class,
        'type'        => 'allowed',
    ]);

    // Create director role for corporation
    $character_role = CharacterRole::factory()->make([
        'character_id' => test()->test_character->character_id,
        'roles' => ['Contract_Manager', 'Director']
    ]);

    $ids = (new GetAffiliatedIdsByPermissionArray(test()->permission->name, 'Director'))->execute();

    // Assert that the owned character_ids are present
    expect(in_array(test()->test_character->character_id, $ids))->toBeTrue();

    // Assert that the corporation_id of test_character with director role is present
    expect(in_array(test()->test_character->corporation->corporation_id, $ids))->toBeTrue();
});

it('returns all character and corporation ids for superuser', function () {
    // give test user superuser
    Permission::create(['name' => 'superuser']);
    test()->test_user->givePermissionTo('superuser');

    // collect all corporation_ids
    $corporation_ids = CorporationInfo::all()->pluck('corporation_id')->values();

    // collect all character_ids
    $character_ids = CharacterInfo::all()->pluck('character_id')->values();

    // get ids
    $ids = (new GetAffiliatedIdsByPermissionArray(test()->permission->name))->execute();

    // check if ids are present
    expect(collect([...$character_ids, ...$corporation_ids])->diff($ids)->isEmpty())->toBeTrue();
});
