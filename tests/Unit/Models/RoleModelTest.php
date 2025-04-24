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

use Seatplus\Auth\Models\Permissions\Affiliation;
use Seatplus\Auth\Models\Permissions\Permission;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Eveapi\Models\Corporation\CorporationInfo;

beforeEach(function () {
    test()->role = Role::create(['name' => 'derp']);
});

it('deletes affiliation after model deletion', function () {
    $affiliation = Affiliation::create([
        'role_id' => test()->role->id,
        'affiliatable_id' => test()->test_character->corporation_id,
        'affiliatable_type' => CorporationInfo::class,
        'type' => 'allowed',
    ]);

    test()->assertDatabaseHas('affiliations', [
        'role_id' => test()->role->id,
    ]);

    test()->role->delete();

    test()->assertDatabaseMissing('affiliations', [
        'role_id' => test()->role->id,
    ]);
});

it('deletes permission pivot after model deletion', function () {
    $permission_name = 'test permission';

    $permission = Permission::create(['name' => $permission_name]);

    test()->role->givePermissionTo($permission_name);

    test()->assertDatabaseHas('role_has_permissions', [
        'role_id' => test()->role->id,
        'permission_id' => $permission->id,
    ]);

    test()->role->delete();

    test()->assertDatabaseMissing('role_has_permissions', [
        'role_id' => test()->role->id,
        'permission_id' => $permission->id,
    ]);
});

it('has polymorphic relation', function () {
    $affiliation = Affiliation::create([
        'role_id' => test()->role->id,
        'affiliatable_id' => test()->test_character->corporation_id,
        'affiliatable_type' => CorporationInfo::class,
        'type' => 'allowed',
    ]);

    expect(test()->role->affiliations->first()->affiliatable::class)->toEqual(CorporationInfo::class);
});

it('has default type attribute', function () {
    expect(test()->role->fresh()->type)->toEqual(\Seatplus\Auth\Enums\RoleType::MANUAL);
});

it('has role memberships', function () {

    \Seatplus\Auth\Models\AccessControl\RoleMembership::query()->create([
        'role_id' => test()->role->id,
        'entity_id' => test()->test_character->corporation_id,
        'entity_type' => CorporationInfo::class,
    ]);

    expect(test()->role->role_memberships->first()->entity)->toBeInstanceOf(CorporationInfo::class);
});
