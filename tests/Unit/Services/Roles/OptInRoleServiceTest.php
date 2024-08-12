<?php

use Seatplus\Auth\Models\AccessControl\RoleMembership;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Models\User;

beforeEach(function (){
    $this->role = Role::create(['name' => 'test']);
    $this->role = $this->role->refresh();

    $this->service = new \Seatplus\Auth\Services\Roles\OptInRoleService($this->role);
});

it('can add criteria', function () {

    $entities = [
        [1, 'corporation']
    ];

    $this->service->addCriteriaForRole($entities);

    expect(RoleMembership::query()->count())->toBe(1)
        ->and(RoleMembership::first())->entity_type->toBe(\Seatplus\Eveapi\Models\Corporation\CorporationInfo::class);
});

it('can join role', function () {
    $test_user = test()->test_user;

    $this->service->joinRole($test_user);

    expect(RoleMembership::query()->count())->toBe(1)
        ->and(RoleMembership::first())->entity_type->toBe(User::class);
});

it('can leave role', function () {
    $test_user = test()->test_user;

    $this->service->joinRole($test_user);

    expect(RoleMembership::query()->count())->toBe(1);

    $this->service->leaveRole($test_user);

    expect(RoleMembership::query()->count())->toBe(0);
});

it('syncs members', function () {
    $test_user = test()->test_user;

    $this->service->joinRole($test_user);

    expect(RoleMembership::first())->status->toBe(\Seatplus\Auth\Enums\RoleMembershipStatus::ACTIVE->value);

    $this->service->syncMembers();

    expect(RoleMembership::count())->toBe(0);
});

describe('it can', function () {
   beforeEach(function (){
       $entities = [
           [test()->test_character->corporation_id, 'corporation']
       ];

       $this->service->addCriteriaForRole($entities);
   });

   it('can view', function () {
       $test_user = test()->test_user;

       expect($this->service->canView($test_user))->toBeTrue();
   });

    it('can join', function () {
         $test_user = test()->test_user;

         expect($this->service->canJoin($test_user))->toBeTrue();
    });
});

it('cannot moderate', function () {
    $test_user = test()->test_user;

    expect($this->service->canModerate($test_user))->toBeFalse();
});
