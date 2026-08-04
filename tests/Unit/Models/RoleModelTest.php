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

use Seatplus\Auth\Enums\RoleType;
use Seatplus\Auth\Models\AccessControl\RoleMembership;
use Seatplus\Auth\Models\Permissions\Affiliation;
use Seatplus\Auth\Models\Permissions\Permission;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Eveapi\Models\Corporation\CorporationInfo;

beforeEach(function () {
    // Spatie annotates Role::create() as RoleContract|SpatieRole, so the subclass is lost.
    /** @var Role $role */
    $role = Role::create(['name' => 'derp']);
    $this->role = $role;
});

it('deletes affiliation after model deletion', function () {
    $affiliation = Affiliation::create([
        'role_id' => $this->role->id,
        'affiliatable_id' => $this->test_character->corporation_id,
        'affiliatable_type' => CorporationInfo::class,
        'type' => 'allowed',
    ]);

    $this->assertDatabaseHas('affiliations', [
        'role_id' => $this->role->id,
    ]);

    $this->role->delete();

    $this->assertDatabaseMissing('affiliations', [
        'role_id' => $this->role->id,
    ]);
});

it('deletes permission pivot after model deletion', function () {
    $permission_name = 'test permission';

    $permission = Permission::create(['name' => $permission_name]);

    $this->role->givePermissionTo($permission_name);

    $this->assertDatabaseHas('role_has_permissions', [
        'role_id' => $this->role->id,
        'permission_id' => $permission->id,
    ]);

    $this->role->delete();

    $this->assertDatabaseMissing('role_has_permissions', [
        'role_id' => $this->role->id,
        'permission_id' => $permission->id,
    ]);
});

it('has polymorphic relation', function () {
    $affiliation = Affiliation::create([
        'role_id' => $this->role->id,
        'affiliatable_id' => $this->test_character->corporation_id,
        'affiliatable_type' => CorporationInfo::class,
        'type' => 'allowed',
    ]);

    expect($this->role->affiliations->first()->affiliatable::class)->toEqual(CorporationInfo::class);
});

it('has default type attribute', function () {
    expect($this->role->fresh()->type)->toEqual(RoleType::MANUAL);
});

it('has role memberships', function () {

    RoleMembership::query()->create([
        'role_id' => $this->role->id,
        'entity_id' => $this->test_character->corporation_id,
        'entity_type' => CorporationInfo::class,
    ]);

    expect($this->role->roleMemberships->first()->entity)->toBeInstanceOf(CorporationInfo::class);
});
