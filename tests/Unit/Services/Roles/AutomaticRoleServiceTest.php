<?php

use Seatplus\Auth\Models\AccessControl\RoleMembership;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Models\User;
use Seatplus\Auth\Services\Roles\AutomaticRoleService;

beforeEach(function () {
    $this->role = Role::create(['name' => 'test']);
    $this->role = $this->role->refresh();
    $this->service = new AutomaticRoleService($this->role);
});

describe('assigning', function () {
    it('role to corporation and getting role on test user', function () {

        $test_character = test()->test_character;
        $corporation_id = $test_character->corporation_id;

        expect(test()->test_user->refresh()->hasRole($this->role->name))->toBeFalse();

        $this->service->automaticallyAssignRoleTo(corporation_ids: [$corporation_id]);

        expect(RoleMembership::get())->toHaveCount(2) // User and Corporation
            ->and(test()->test_user->refresh()->hasRole($this->role->name))->toBeTrue();

    });

    it('role to alliance', function () {

        $test_character = test()->test_character;
        $alliance_id = $test_character->alliance_id;

        $this->service->automaticallyAssignRoleTo(alliance_ids: [$alliance_id]);

        expect(test()->test_user->refresh()->hasRole($this->role->name))->toBeTrue();
    });

    it('role to corporation and alliance', function () {

        $test_character = test()->test_character;
        $corporation_id = $test_character->corporation_id;
        $alliance_id = $test_character->alliance_id;

        $this->service->automaticallyAssignRoleTo(corporation_ids: [$corporation_id], alliance_ids: [$alliance_id]);

        expect(RoleMembership::get())->toHaveCount(3) // User, Corporation and Alliance
            ->and(test()->test_user->refresh()->hasRole($this->role->name))->toBeTrue();
    });
});

describe('handling Members', function () {
    it('removes role from user if nothing is assigned', function () {

        $test_user = test()->test_user;

        $test_user->assignRole($this->role);

        expect(test()->test_user->refresh()->hasRole($this->role->name))->toBeTrue();

        $this->service->automaticallyAssignRoleTo();

        expect(test()->test_user->refresh()->hasRole($this->role->name))->toBeFalse()
            ->and(RoleMembership::query()->count())->toBe(0);
    });

    it('works also with role in constructor', function () {
        /** @var Role $role */
        $role = Role::create(['name' => 'constructor test']);

        $service = new AutomaticRoleService($role);

        $test_character = test()->test_character;
        $corporation_id = $test_character->corporation_id;

        $service->automaticallyAssignRoleTo(corporation_ids: [$corporation_id]);

        expect(RoleMembership::get())->toHaveCount(2) // User and Corporation
            ->and(test()->test_user->refresh()->hasRole($role->name))->toBeTrue();
    });
});

it('sets role type to automatic', function () {

    expect($this->role->type)->toBe('manual');

    $this->service->automaticallyAssignRoleTo();

    expect($this->role->type)->toBe('automatic');
});
