<?php

use Illuminate\Support\Str;
use Seatplus\Auth\Models\Permissions\Permission;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Services\Permissions\RolePermissionObjectService;
use Seatplus\Auth\Services\Roles\RoleAffiliatedIdsService;

test('role permission object service', function () {
    // Arrange
    /** @var Role $role */
    $role = Role::create(['name' => Str::random()]);

    // create 3 permissions
    $permissions = [
        Permission::create(['name' => Str::random()]),
        Permission::create(['name' => Str::random()]),
        Permission::create(['name' => Str::random()]),
    ];

    $role->syncPermissions($permissions);

    $mock = mock(RoleAffiliatedIdsService::class, function ($mock) {
        $mock->shouldReceive('get')->andReturn([1, 2, 3]);
    });

    // Act
    $role_permission_object_service = new RolePermissionObjectService($mock);

    $result = $role_permission_object_service->get($role);

    // Assert
    expect($result)->toHaveCount(3)
        ->toHaveKeys([$permissions[0]->name, $permissions[1]->name, $permissions[2]->name])
        ->and($result[$permissions[0]->name])->toBe([1, 2, 3])
        ->and($result[$permissions[1]->name])->toBe([1, 2, 3])
        ->and($result[$permissions[2]->name])->toBe([1, 2, 3]);
});
