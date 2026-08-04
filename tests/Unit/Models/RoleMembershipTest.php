<?php

use Seatplus\Auth\Models\AccessControl\RoleMembership;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Eveapi\Models\Corporation\CorporationInfo;

it('has role relationship', function () {

    // Arrange
    $role = Role::create(['name' => 'test role']);

    RoleMembership::query()->create([
        'role_id' => $role->id,
        'entity_id' => $this->test_character->corporation_id,
        'entity_type' => CorporationInfo::class,
    ]);

    // Act
    $role_membership = RoleMembership::first();

    // Assert
    expect($role_membership->role->name)->toEqual('test role');

});
