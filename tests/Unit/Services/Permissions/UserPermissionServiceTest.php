<?php

use Seatplus\Auth\Models\Permissions\Permission;
use Seatplus\Auth\Models\Permissions\Role;
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

    CharacterRole::query()->delete();

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

it('builds permission_roles from user', function () {

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

    // permission[0] only on role1; permission[1] on both; permission[2] only on role2
    $role1->syncPermissions($permissions->take(2));
    $role2->syncPermissions($permissions->slice(1));

    $user->assignRole([$role1, $role2]);

    // Act
    $result = (new UserPermissionService)->get($user);

    // Assert: each permission maps to the ids of the user's roles that grant it
    expect($result['permission_roles'])
        ->toHaveKeys($permissions->pluck('name')->toArray())
        ->and($result['permission_roles'][$permissions[0]->name])->toBe([$role1->id])
        ->and($result['permission_roles'][$permissions[1]->name])->toContain($role1->id, $role2->id)
        ->and($result['permission_roles'][$permissions[2]->name])->toBe([$role2->id]);
});

describe('cache user permissions', function () {})->only();
