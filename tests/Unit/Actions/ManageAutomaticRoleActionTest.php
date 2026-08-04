<?php

use Mockery\MockInterface;
use Seatplus\Auth\Enums\AffiliationType;
use Seatplus\Auth\Enums\RoleType;
use Seatplus\Auth\Http\Actions\Roles\ManageAutomaticRoleAction;
use Seatplus\Auth\Http\Requests\RoleRequest;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Models\User;
use Seatplus\Auth\Services\Roles\AutomaticRoleService;
use Seatplus\Auth\Services\Roles\BaseRoleService;
use Seatplus\Auth\Services\Roles\DTO\AffiliationData;
use Seatplus\Auth\Services\Roles\DTO\CriteriaData;

it('throws exception when user is missing permission', function () {
    $request = mock(RoleRequest::class);

    $this->actingAs($this->test_user);

    $action = app(ManageAutomaticRoleAction::class);
    $action->execute($request);
})->throws(Exception::class, 'You are not allowed to administrate access control groups');

it('invokes role service with valid role id', function () {
    $role = Role::create(['name' => 'test']);

    $request = mock(RoleRequest::class, function (MockInterface $mock) use ($role) {
        $mock->shouldReceive('validated')->once()->andReturn(['role_id' => $role->refresh()->id, 'affiliated' => [], 'assigned' => []]);
    });

    $admin_permission = 'administrate access control groups';

    // give the user the permission to administrate access control groups
    assignPermissionToTestUser($admin_permission);

    $this->actingAs($this->test_user);

    /** @var User $authenticated_user */
    $authenticated_user = auth()->user();

    expect($this->test_user->hasPermissionTo($admin_permission))->toBeTrue()
        ->and($authenticated_user->hasPermissionTo($admin_permission))->toBeTrue()
        ->and($authenticated_user->can($admin_permission))->toBeTrue();

    $action = app(ManageAutomaticRoleAction::class);
    $action->execute($request);
});

it('invokes role service with affiliated entities', function () {
    $request = mock(RoleRequest::class, function (MockInterface $mock) {
        $mock->shouldReceive('validated')->andReturn(['role_id' => 1, 'affiliated' => [['entity_id' => 1, 'entity_type' => 'corporation', 'affiliation_type' => 'allowed']], 'assigned' => []]);
    });

    $this->mock(BaseRoleService::class, function (MockInterface $mock) {
        $mock->shouldReceive('for')->with(1)->andReturn($mock);
        $mock->shouldReceive('automatic')->andReturn(mock(AutomaticRoleService::class, function (MockInterface $mock) {
            $mock->shouldReceive('setRoleType')->once()->with(RoleType::AUTOMATIC);
            $mock->shouldReceive('syncAffiliateManyEntities')->once()->withArgs(fn (AffiliationData $entity) => $entity->entity_id === 1 && $entity->entity_type === 'corporation' && $entity->affiliation_type === AffiliationType::ALLOWED);
            // assigned: [] → empty array → automaticallyAssignRoleTo called with 0 args (clears criteria)
            $mock->shouldReceive('automaticallyAssignRoleTo')->once()->withNoArgs();
            $mock->shouldReceive('handleMembers')->once();
        }));
    });

    $this->actingAs($this->test_user);
    // give the user the permission to administrate access control groups
    assignPermissionToTestUser('administrate access control groups');

    $action = app(ManageAutomaticRoleAction::class);
    $action->execute($request);
});

it('invokes role service with assigned entities', function () {
    $request = mock(RoleRequest::class, function (MockInterface $mock) {
        $mock->shouldReceive('validated')->andReturn(['role_id' => 1, 'affiliated' => [], 'assigned' => [['entity_id' => 1, 'entity_type' => 'corporation']]]);
    });

    $this->mock(BaseRoleService::class, function (MockInterface $mock) {
        $mock->shouldReceive('for')->once()->with(1)->andReturn($mock);
        $mock->shouldReceive('automatic')->andReturn(mock(AutomaticRoleService::class, function (MockInterface $mock) {
            $mock->shouldReceive('setRoleType')->once()->with(RoleType::AUTOMATIC);
            // affiliated: [] → empty array → syncAffiliateManyEntities called with 0 args (clears scope)
            $mock->shouldReceive('syncAffiliateManyEntities')->once()->withNoArgs();
            $mock->shouldReceive('automaticallyAssignRoleTo')->once()->withArgs(fn (CriteriaData $entity) => $entity->entity_id === 1 && $entity->entity_type === 'corporation');
            $mock->shouldReceive('handleMembers')->once();
        }));
    });

    $this->actingAs($this->test_user);
    // give the user the permission to administrate access control groups
    assignPermissionToTestUser('administrate access control groups');

    $action = app(ManageAutomaticRoleAction::class);
    $action->execute($request);
});

it('updates name of role', function () {
    $request = mock(RoleRequest::class, function (MockInterface $mock) {
        $mock->shouldReceive('validated')->andReturn(['role_id' => 1, 'name' => 'new name', 'affiliated' => [], 'assigned' => []]);
    });

    $this->mock(BaseRoleService::class, function (MockInterface $mock) {
        $mock->shouldReceive('for')->once()->with(1)->andReturn($mock);
        $mock->shouldReceive('automatic')->andReturn(mock(AutomaticRoleService::class, function (MockInterface $mock) {
            $mock->shouldReceive('setRoleType')->once()->with(RoleType::AUTOMATIC);
            $mock->shouldReceive('updateRoleName')->once()->with('new name');
            // affiliated: [] and assigned: [] → both called with 0 args
            $mock->shouldReceive('syncAffiliateManyEntities')->once()->withNoArgs();
            $mock->shouldReceive('automaticallyAssignRoleTo')->once()->withNoArgs();
            $mock->shouldReceive('handleMembers')->once();
        }));
    });

    $this->actingAs($this->test_user);
    // give the user the permission to administrate access control groups
    assignPermissionToTestUser('administrate access control groups');

    $action = app(ManageAutomaticRoleAction::class);
    $action->execute($request);
});
