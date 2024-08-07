<?php

use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Services\Roles\BaseRoleService;

beforeEach(function () {
    $this->role = Role::create(['name' => faker()->name()]);
    $this->role = $this->role->refresh();
    $this->service = new BaseRoleService();
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
    })->expectException(\Spatie\Permission\Exceptions\RoleDoesNotExist::class);
});

it('can get automatic role service', function () {

    $service = BaseRoleService::make($this->role)->automatic();

    expect($service)->toBeInstanceOf(\Seatplus\Auth\Services\Roles\AutomaticRoleService::class);
});
