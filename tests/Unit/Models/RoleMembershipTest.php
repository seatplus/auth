<?php

use Seatplus\Eveapi\Models\Corporation\CorporationInfo;

it('has role relationship', function () {

    // Arrange
    $role = \Seatplus\Auth\Models\Permissions\Role::create(['name' => 'test role']);

    \Seatplus\Auth\Models\AccessControl\RoleMembership::query()->create([
        'role_id' => $role->id,
        'entity_id' => test()->test_character->corporation_id,
        'entity_type' => CorporationInfo::class,
    ]);

    // Act
    $role_membership = \Seatplus\Auth\Models\AccessControl\RoleMembership::first();

    // Assert
    expect($role_membership->role->name)->toEqual('test role');

});
