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
use Seatplus\Auth\Tests\TestCase;
use Seatplus\Eveapi\Models\Character\CharacterInfo;
use Seatplus\Eveapi\Models\Corporation\CorporationInfo;

uses(TestCase::class);

beforeEach(function () {
    test()->role = Role::create(['name' => 'derp']);
});

it('deletes affiliation after model deletion', function () {


    $affiliation = Affiliation::create([
        'role_id' => test()->role->id,
        'affiliatable_id' => test()->test_character->corporation_id,
        'affiliatable_type' => CorporationInfo::class,
        'type' => 'allowed'
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
        'role_id'       => test()->role->id,
        'permission_id' => $permission->id,
    ]);

    test()->role->delete();

    test()->assertDatabaseMissing('role_has_permissions', [
        'role_id'       => test()->role->id,
        'permission_id' => $permission->id,
    ]);
});

it('has polymorphic relation', function () {

    $affiliation = Affiliation::create([
        'role_id' => test()->role->id,
        'affiliatable_id' => test()->test_character->corporation_id,
        'affiliatable_type' => CorporationInfo::class,
        'type' => 'allowed'
    ]);

    expect(test()->role->affiliations->first()->affiliatable::class)->toEqual(CorporationInfo::class);

});

it('has default type attribute', function () {

    expect(test()->role->fresh()->type)->toEqual('manual');
});

it('has acl affiliations', function () {

    test()->role->acl_affiliations()->create([
        'affiliatable_id' => test()->test_character->character_id,
        'affiliatable_type' => CharacterInfo::class,
    ]);

    expect(test()->role->acl_affiliations->first()->affiliatable::class)->toEqual(CharacterInfo::class);
});

it('has acl moderators', function () {

    test()->role->acl_affiliations()->create([
        'affiliatable_id' => test()->test_character->character_id,
        'affiliatable_type' => CharacterInfo::class,
        'can_moderate' => true
    ]);

    expect(test()->role->acl_affiliations->isEmpty())->toBeTrue();

    expect(test()->role->moderators->first()->affiliatable::class)->toEqual(CharacterInfo::class);
});

it('has acl members', function () {

    test()->role->members()->create([
        'user_id' => test()->test_user->id,
        'status' => 'member'
    ]);

    expect(test()->role->members->isNotEmpty())->toBeTrue();
});

test('one can add member', function () {
    test()->role->activateMember(test()->test_user);

    expect(test()->role->members->isNotEmpty())->toBeTrue();
});

test('one can pause member', function () {
    test()->role->activateMember(test()->test_user);

    expect(test()->role->members->isNotEmpty())->toBeTrue();

    test()->role->pauseMember(test()->test_user);

    expect(test()->role->refresh()->members->isEmpty())->toBeTrue();
});

test('one can remove member', function () {
    test()->role->activateMember(test()->test_user);

    expect(test()->role->members->isNotEmpty())->toBeTrue();

    test()->role->removeMember(test()->test_user);

    expect(test()->role->refresh()->members->isEmpty())->toBeTrue();
});

it('throws error if unaffiliated user wants to join', function () {
    $role = Role::create(['name' => 'test', 'type' => 'on-request']);

    test()->expectExceptionMessage('User is not allowed for this access control group');

    $role->activateMember(test()->test_user);
});

it('throws error if one tries to join waitlist on invalid role', function () {

    test()->expectExceptionMessage('Only on-request control groups do have a waitlist');

    test()->role->joinWaitlist(test()->test_user);
});

it('throws error if unaffiliated user tries to join waitlist', function () {

    $role = Role::create(['name' => 'test', 'type' => 'on-request']);

    test()->expectExceptionMessage('User is not allowed for this access control group');

    $role->joinWaitlist(test()->test_user);
});

test('user can join waitlist', function () {

    $role = Role::create(['name' => 'test', 'type' => 'on-request']);

    $role->acl_affiliations()->create([
        'affiliatable_id' => test()->test_character->character_id,
        'affiliatable_type' => CharacterInfo::class
    ]);

    $role->joinWaitlist(test()->test_user);

    expect($role->refresh()->acl_members()->whereStatus('waitlist')->first()->user_id)->toEqual(test()->test_user->id);
});

test('one can get moderator ids', function () {

    $role = Role::create(['name' => 'test', 'type' => 'on-request']);

    $role->acl_affiliations()->create([
        'affiliatable_id' => test()->test_character->character_id,
        'affiliatable_type' => CharacterInfo::class,
        'can_moderate' => true
    ]);

    expect($role->refresh()->isModerator(test()->test_user))->toBeTrue();
});
