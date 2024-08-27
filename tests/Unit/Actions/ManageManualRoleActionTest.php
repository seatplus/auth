<?php

use Seatplus\Auth\Http\Actions\Roles\Manual\ManageManualRoleAction;
use Seatplus\Auth\Http\Requests\RoleRequest;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Services\Roles\BaseRoleService;

it('sets the role type to manual', function () {
    $role = Role::create(['name' => 'test_role']);

    $role_request = mock(RoleRequest::class, function ($mock) use ($role) {
        $mock->shouldReceive('validated')
            ->andReturn(['role_id' => $role->id]);
    });

    $this->mock(BaseRoleService::class, function ($mock) use ($role) {
        $mock->shouldReceive('for')
            ->with($role->id)
            ->andReturn($mock);

        $mock->shouldReceive('manual')
            ->andReturn(mock(\Seatplus\Auth\Services\Roles\ManualRoleService::class, function (\Mockery\MockInterface $mock) {
                $mock->shouldReceive('setRoleType');
            }));
    });

    $action = app(ManageManualRoleAction::class);

    $action->execute($role_request);

    expect($role->refresh()->type)->toBe('manual');
});

it('updates the role name', function () {
    $role = Role::create(['name' => 'test_role']);

    $role_request = mock(RoleRequest::class, function ($mock) use ($role) {
        $mock->shouldReceive('validated')
            ->andReturn(['role_id' => $role->id, 'name' => 'new_name']);
    });

    $this->mock(BaseRoleService::class, function ($mock) use ($role) {
        $mock->shouldReceive('for')
            ->with($role->id)
            ->andReturn($mock);

        $mock->shouldReceive('manual')
            ->andReturn(mock(\Seatplus\Auth\Services\Roles\ManualRoleService::class, function (\Mockery\MockInterface $mock) {
                $mock->shouldReceive('setRoleType');
                $mock->shouldReceive('updateRoleName')->once();
            }));
    });

    $action = app(ManageManualRoleAction::class);

    $action->execute($role_request);
});

it('affiliates many entities', function () {
    $role = Role::create(['name' => 'test_role']);

    $role_request = mock(RoleRequest::class, function ($mock) use ($role) {
        $mock->shouldReceive('validated')
            ->andReturn(['role_id' => $role->id, 'affiliated' => [['entity_id' => 1, 'entity_type' => 'corporation', 'type' => 'member']]]);
    });

    $this->mock(BaseRoleService::class, function ($mock) use ($role) {
        $mock->shouldReceive('for')
            ->with($role->id)
            ->andReturn($mock);

        $mock->shouldReceive('manual')
            ->andReturn(mock(\Seatplus\Auth\Services\Roles\ManualRoleService::class, function (\Mockery\MockInterface $mock) {
                $mock->shouldReceive('setRoleType');
                $mock->shouldReceive('syncAffiliateManyEntities')->once();
            }));
    });

    $action = app(ManageManualRoleAction::class);

    $action->execute($role_request);
});
