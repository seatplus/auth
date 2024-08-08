<?php

use Seatplus\Auth\Models\Permissions\Permission;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Services\Permissions\RolePermissionObjectService;
use Seatplus\Auth\Services\Permissions\UserPermissionService;
use Seatplus\Eveapi\Models\Character\CharacterRole;

it('builds owned_character_ids from user', function () {

    // Arrange
    $user = test()->test_user;

    // Act
    $user_permission_service = new UserPermissionService;

    $result = $user_permission_service->get($user);

    // Assert
    expect($result['owned_character_ids'])->toBe($user->characters->pluck('character_id')->toArray());
});

it('builds corporation_roles from user', function () {

    // Arrange
    $user = test()->test_user;

    CharacterRole::factory()->create([
        'character_id' => test()->test_character->character_id,
        'roles' => ['Director', 'Personnel Manager'],
    ]);

    // Act
    $user_permission_service = new UserPermissionService;

    $result = $user_permission_service->get($user);

    // Assert
    expect($result['corporation_roles'])
        ->toHaveCount(2)
        ->toHaveKey('Director')
        ->toHaveKey('Personnel Manager')
        ->and($result['corporation_roles']['Director'])->toContain(test()->test_character->corporation_id)
        ->and($result['corporation_roles']['Personnel Manager'])->toContain(test()->test_character->corporation_id);
});

it('builds permissions from user', function () {

    // Arrange
    $user = test()->test_user;

    $role1 = Role::create(['name' => Str::random()]);
    $role2 = Role::create(['name' => Str::random()]);

    // create 3 permissions
    $permissions = collect([
        Permission::create(['name' => Str::random()]),
        Permission::create(['name' => Str::random()]),
        Permission::create(['name' => Str::random()]),
    ]);

    // sync first two permissions to role1
    $role1->syncPermissions($permissions->take(2));

    // sync last 2 permission to role2
    $role2->syncPermissions($permissions->slice(1));

    $user->assignRole([$role1, $role2]);

    $role_permission_object_service = mock(RolePermissionObjectService::class, function (\Mockery\MockInterface $mock) use ($permissions) {

        $result1 = collect([
            $permissions[0]->name => [1, 2, 3],
            $permissions[1]->name => [4, 5, 6],
        ]);

        $result2 = collect([
            $permissions[1]->name => [7, 8, 9],
            $permissions[2]->name => [10, 11, 12],
        ]);

        $mock->shouldReceive('get')
            //->with($role1)
            ->andReturn($result1, $result2);
    });

    // Act
    $user_permission_service = new UserPermissionService($role_permission_object_service);

    $result = $user_permission_service->get($user);

    // Assert
    expect($result['permissions'])
        ->toHaveCount(3)
        ->toHaveKeys($permissions->pluck('name')->toArray())
        ->and($result['permissions'][$permissions[0]->name])->toBe([1, 2, 3])
        ->and($result['permissions'][$permissions[1]->name])->toContain(4, 5, 6, 7, 8, 9)
        ->and($result['permissions'][$permissions[2]->name])->toBe([10, 11, 12]);
});

describe('cache user permissions', function () {

})->only();
