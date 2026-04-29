<?php

declare(strict_types=1);

use Mockery\MockInterface;
use Seatplus\Auth\Enums\AffiliationType;
use Seatplus\Auth\Enums\RoleType;
use Seatplus\Auth\Http\Actions\Roles\Manual\ManageManualRoleAction;
use Seatplus\Auth\Http\Requests\RoleRequest;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Services\Roles\BaseRoleService;
use Seatplus\Auth\Services\Roles\DTO\AffiliationData;
use Seatplus\Auth\Services\Roles\ManualRoleService;

it('sets the role type to manual', function () {
    $role = Role::create(['name' => 'test_role']);

    $role_request = mock(RoleRequest::class, function (MockInterface $mock) use ($role) {
        $mock->shouldReceive('validated')
            ->andReturn(['role_id' => $role->id]);
    });

    $this->mock(BaseRoleService::class, function (MockInterface $mock) use ($role) {
        $mock->shouldReceive('for')
            ->with($role->id)
            ->andReturn($mock);

        $mock->shouldReceive('manual')
            ->andReturn(mock(ManualRoleService::class, function (MockInterface $mock) {
                $mock->shouldReceive('setRoleType')->once();
                $mock->shouldReceive('handleMembers')->once();
            }));
    });

    $action = app(ManageManualRoleAction::class);

    $action->execute($role_request);

    expect($role->refresh()->type)->toBe(RoleType::MANUAL);
});

it('updates the role name', function () {
    $role = Role::create(['name' => 'test_role']);

    $role_request = mock(RoleRequest::class, function (MockInterface $mock) use ($role) {
        $mock->shouldReceive('validated')
            ->andReturn(['role_id' => $role->id, 'name' => 'new_name']);
    });

    $this->mock(BaseRoleService::class, function (MockInterface $mock) use ($role) {
        $mock->shouldReceive('for')
            ->with($role->id)
            ->andReturn($mock);

        $mock->shouldReceive('manual')
            ->andReturn(mock(ManualRoleService::class, function (MockInterface $mock) {
                $mock->shouldReceive('setRoleType')->once();
                $mock->shouldReceive('updateRoleName')->once()->with('new_name');
                $mock->shouldReceive('handleMembers')->once();
            }));
    });

    $action = app(ManageManualRoleAction::class);

    $action->execute($role_request);
});

it('affiliates many entities', function () {
    $role = Role::create(['name' => 'test_role']);

    $role_request = mock(RoleRequest::class, function (MockInterface $mock) use ($role) {
        $mock->shouldReceive('validated')
            ->andReturn(['role_id' => $role->id, 'affiliated' => [['entity_id' => 1, 'entity_type' => 'corporation', 'affiliation_type' => 'allowed']]]);
    });

    $this->mock(BaseRoleService::class, function (MockInterface $mock) use ($role) {
        $mock->shouldReceive('for')
            ->with($role->id)
            ->andReturn($mock);

        $mock->shouldReceive('manual')
            ->andReturn(mock(ManualRoleService::class, function (MockInterface $mock) {
                $mock->shouldReceive('setRoleType')->once();
                $mock->shouldReceive('syncAffiliateManyEntities')->once()->withArgs(function (AffiliationData $affiliationData) {
                    return $affiliationData->entity_id === 1
                        && $affiliationData->entity_type === 'corporation'
                        && $affiliationData->affiliation_type === AffiliationType::ALLOWED;
                });
                $mock->shouldReceive('handleMembers')->once();
            }));
    });

    $action = app(ManageManualRoleAction::class);

    $action->execute($role_request);
});
