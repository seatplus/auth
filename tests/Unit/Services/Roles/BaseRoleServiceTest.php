<?php

use Seatplus\Auth\Enums\RoleType;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Services\Roles\AutomaticRoleService;
use Seatplus\Auth\Services\Roles\BaseRoleService;
use Spatie\Permission\Exceptions\RoleDoesNotExist;

beforeEach(function () {
    // Spatie annotates Role::create() as RoleContract|SpatieRole, so the subclass is lost.
    /** @var Role $role */
    $role = Role::create(['name' => faker()->name()]);
    $this->role = $role->refresh();
    $this->service = new BaseRoleService;
});

describe('make', function () {
    // make() is declared : self, so asserting the return type proves nothing — assert
    // instead that for() actually resolved the role behind the service.
    test('service can be made role', function () {

        $service = BaseRoleService::make($this->role);

        expect($service->getType())->toBe($this->role->type);
    });

    test('service can be made role by id', function () {

        $service = BaseRoleService::make($this->role->id);

        expect($service->getType())->toBe($this->role->type);
    });

    it('throws exception if role not found', function () {

        BaseRoleService::make('abc');
    })->throws(RoleDoesNotExist::class);
});

it('can get automatic role service', function () {

    $service = BaseRoleService::make($this->role)->automatic();

    // automatic() is declared : AutomaticRoleService, so assert the service is actually
    // bound to our role rather than re-asserting its return type.
    $service->setRoleType(RoleType::AUTOMATIC);

    expect($this->role->refresh()->type)->toBe(RoleType::AUTOMATIC);
});

it('work with the various role types', function (RoleType $role_type) {
    // Arrange
    $this->role->update(['type' => $role_type->value]);

    $service = BaseRoleService::make($this->role->refresh());

    $service->for($this->role);

    // act
    $service->handleMembers();

    $can_view = $service->canView($this->test_user);
    $can_join = $service->canJoin($this->test_user);
    $can_moderate = $service->canModerate($this->test_user);

    // assert
    expect($can_view)->toBeFalse()
        ->and($can_join)->toBeFalse()
        ->and($can_moderate)->toBeFalse();

})->with(RoleType::cases());
