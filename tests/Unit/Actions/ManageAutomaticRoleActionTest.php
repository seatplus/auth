<?php

use Mockery\MockInterface;
use Seatplus\Auth\Http\Requests\RoleRequest;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Services\Roles\AutomaticRoleService;
use Seatplus\Auth\Services\Roles\BaseRoleService;

it('invokes role service with valid role id', function () {
    $role = Role::create(['name' => 'test']);

    $request = mock(RoleRequest::class, function (MockInterface $mock) use ($role) {
        $mock->shouldReceive('validated')->once()->andReturn(['role_id' => $role->refresh()->id, 'affiliated' => [], 'assigned' => []]);
    });

    $action = new \Seatplus\Auth\Http\Actions\Roles\ManageAutomaticRoleAction();

    $action($request);
});

it('invokes role service with affiliated entities', function () {
    $request = mock(RoleRequest::class, function (MockInterface $mock) {
        $mock->shouldReceive('validated')->andReturn(['role_id' => 1, 'affiliated' => [['entity_id' => 1, 'entity_type' => 'corporation', 'type' => 'member']], 'assigned' => []]);
    });

    $baseRoleService = mock(BaseRoleService::class, function (MockInterface $mock) {
        $mock->shouldReceive('for')->with(1);
        $mock->shouldReceive('automatic')->andReturn(mock(AutomaticRoleService::class, function (MockInterface $mock) {
            $mock->shouldReceive('syncAffiliateManyEntities')->once()->with([['entity_id' => 1, 'entity_type' => 'corporation', 'type' => 'member']]);
        }));
    });

    $action = new \Seatplus\Auth\Http\Actions\Roles\ManageAutomaticRoleAction($baseRoleService);

    $action($request);
});

it('invokes role service with assigned entities', function () {
    $request = mock(RoleRequest::class, function (MockInterface $mock) {
        $mock->shouldReceive('validated')->andReturn(['role_id' => 1, 'affiliated' => [], 'assigned' => [['entity_id' => 1, 'entity_type' => 'corporation', 'type' => 'member']]]);
    });

    $baseRoleService = mock(BaseRoleService::class, function (MockInterface $mock) {
        $mock->shouldReceive('for')->once()->with(1);
        $mock->shouldReceive('automatic')->andReturn(mock(AutomaticRoleService::class, function (MockInterface $mock) {
            $mock->shouldReceive('automaticallyAssignRoleTo')->once();
        }));
    });

    $action = new \Seatplus\Auth\Http\Actions\Roles\ManageAutomaticRoleAction($baseRoleService);

    $action($request);
});
