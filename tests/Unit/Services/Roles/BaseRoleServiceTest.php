<?php

use Seatplus\Auth\Enums\RoleType;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Services\Roles\AutomaticRoleService;
use Seatplus\Auth\Services\Roles\BaseRoleService;
use Spatie\Permission\Exceptions\RoleDoesNotExist;

beforeEach(function () {
    $this->role = Role::create(['name' => faker()->name()]);
    $this->role = $this->role->refresh();
    $this->service = new BaseRoleService;
});

describe('make', function () {
    test('service can be made role', function () {

        $service = BaseRoleService::make($this->role);

        expect($service)->toBeInstanceOf(BaseRoleService::class);
    });

    test('service can be made role by id', function () {

        $service = BaseRoleService::make($this->role->id);

        expect($service)->toBeInstanceOf(BaseRoleService::class);
    });

    it('throws exception if role not found', function () {

        BaseRoleService::make('abc');
    })->expectException(RoleDoesNotExist::class);
});

it('can get automatic role service', function () {

    $service = BaseRoleService::make($this->role)->automatic();

    expect($service)->toBeInstanceOf(AutomaticRoleService::class);
});

it('work with the various role types', function (RoleType $role_type) {
    // Arrange
    $this->role->update(['type' => $role_type->value]);

    $service = BaseRoleService::make($this->role->refresh());

    $service->for($this->role);

    // act
    $service->handleMembers();

    $can_view = $service->canView(test()->test_user);
    $can_join = $service->canJoin(test()->test_user);
    $can_moderate = $service->canModerate(test()->test_user);

    // assert
    expect($can_view)->toBeFalse()
        ->and($can_join)->toBeFalse()
        ->and($can_moderate)->toBeFalse();

})->with(RoleType::cases());
