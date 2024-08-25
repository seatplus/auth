<?php

use Mockery\MockInterface;
use Seatplus\Auth\Http\Requests\RoleRequest;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Services\Roles\AutomaticRoleService;
use Seatplus\Auth\Services\Roles\BaseRoleService;

it('throws exception when user is missing permission', function () {
    $request = mock(RoleRequest::class);

    $this->actingAs(test()->test_user);

    $action = app(\Seatplus\Auth\Http\Actions\Roles\ManageAutomaticRoleAction::class);
    $action->execute($request);
})->throws(\Exception::class, 'You are not allowed to administrate access control groups');

it('invokes role service with valid role id', function () {
    $role = Role::create(['name' => 'test']);

    $request = mock(RoleRequest::class, function (MockInterface $mock) use ($role) {
        $mock->shouldReceive('validated')->once()->andReturn(['role_id' => $role->refresh()->id, 'affiliated' => [], 'assigned' => []]);
    });

    $this->actingAs(test()->test_user);

    // give the user the permission to administrate access control groups
    assignPermissionToTestUser('administrate access control groups');

    $action = app(\Seatplus\Auth\Http\Actions\Roles\ManageAutomaticRoleAction::class);
    $action->execute($request);
});

it('invokes role service with affiliated entities', function () {
    $request = mock(RoleRequest::class, function (MockInterface $mock) {
        $mock->shouldReceive('validated')->andReturn(['role_id' => 1, 'affiliated' => [['entity_id' => 1, 'entity_type' => 'corporation', 'type' => 'member']], 'assigned' => []]);
    });

    $this->mock(BaseRoleService::class, function (MockInterface $mock) {
        $mock->shouldReceive('for')->with(1);
        $mock->shouldReceive('automatic')->andReturn(mock(AutomaticRoleService::class, function (MockInterface $mock) {
            $mock->shouldReceive('syncAffiliateManyEntities')->once()->with([['entity_id' => 1, 'entity_type' => 'corporation', 'type' => 'member']]);
            $mock->shouldReceive('setRoleType')->once()->with(\Seatplus\Auth\Enums\RoleType::AUTOMATIC);
        }));

    });

    $this->actingAs(test()->test_user);
    // give the user the permission to administrate access control groups
    assignPermissionToTestUser('administrate access control groups');

    $action = app(\Seatplus\Auth\Http\Actions\Roles\ManageAutomaticRoleAction::class);
    $action->execute($request);
});

it('invokes role service with assigned entities', function () {
    $request = mock(RoleRequest::class, function (MockInterface $mock) {
        $mock->shouldReceive('validated')->andReturn(['role_id' => 1, 'affiliated' => [], 'assigned' => [['entity_id' => 1, 'entity_type' => 'corporation', 'type' => 'member']]]);
    });

    $this->mock(BaseRoleService::class, function (MockInterface $mock) {
        $mock->shouldReceive('for')->once()->with(1);
        $mock->shouldReceive('automatic')->andReturn(mock(AutomaticRoleService::class, function (MockInterface $mock) {
            $mock->shouldReceive('automaticallyAssignRoleTo')->once();
            $mock->shouldReceive('setRoleType')->once()->with(\Seatplus\Auth\Enums\RoleType::AUTOMATIC);
        }));
    });

    $this->actingAs(test()->test_user);
    // give the user the permission to administrate access control groups
    assignPermissionToTestUser('administrate access control groups');

    $action = app(\Seatplus\Auth\Http\Actions\Roles\ManageAutomaticRoleAction::class);
    $action->execute($request);
});

it('updates name of role', function () {
    $request = mock(RoleRequest::class, function (MockInterface $mock) {
        $mock->shouldReceive('validated')->andReturn(['role_id' => 1, 'name' => 'new name', 'affiliated' => [], 'assigned' => []]);
    });

    $this->mock(BaseRoleService::class, function (MockInterface $mock) {
        $mock->shouldReceive('for')->once()->with(1);
        $mock->shouldReceive('automatic')->andReturn(mock(AutomaticRoleService::class, function (MockInterface $mock) {
            $mock->shouldReceive('updateRoleName')->once()->with('new name');
            $mock->shouldReceive('setRoleType')->once()->with(\Seatplus\Auth\Enums\RoleType::AUTOMATIC);
        }));
    });

    $this->actingAs(test()->test_user);
    // give the user the permission to administrate access control groups
    assignPermissionToTestUser('administrate access control groups');

    $action = app(\Seatplus\Auth\Http\Actions\Roles\ManageAutomaticRoleAction::class);
    $action->execute($request);
});
