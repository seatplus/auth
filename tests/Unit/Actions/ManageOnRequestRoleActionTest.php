<?php

use Mockery\MockInterface;
use Seatplus\Auth\Http\Actions\Roles\OnRequest\ManageOnRequestRoleAction;
use Seatplus\Auth\Http\Requests\RoleRequest;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Services\Roles\BaseRoleService;
use Seatplus\Auth\Services\Roles\OnRequestRoleService;

it('executes manage on request role action successfully', function () {

    $role = Role::create(['name' => 'Role Name']);

    $request = mock(RoleRequest::class, function (MockInterface $mock) use ($role) {

        $mock->shouldReceive('validated')->once()->andReturn([
            'role_id' => $role->refresh()->id,
            'affiliated' => ['entity1', 'entity2'],
            'assigned' => ['criteria1', 'criteria2'],
            'name' => 'New Role Name'
        ]);
    });

    $this->mock(BaseRoleService::class, function ($mock) use ($role) {
        $mock->shouldReceive('for')
            ->with($role->id)
            ->andReturn($mock);

        $mock->shouldReceive('onRequest')->andReturn(mock(OnRequestRoleService::class, function ($mock) {
            $mock->shouldReceive('syncAffiliateManyEntities')->once()->with(['entity1', 'entity2']);
            $mock->shouldReceive('addCriteriaForRoleApplication')->once();
            $mock->shouldReceive('updateRoleName')->once();
            $mock->shouldReceive('setRoleType')->with(\Seatplus\Auth\Enums\RoleType::ON_REQUEST)->once();
        }));
    });

    // give the user the permission to manage roles
    assignPermissionToTestUser('administrate access control groups');
    $this->actingAs($this->test_user->refresh());

    $action = app(ManageOnRequestRoleAction::class);
    $action->execute($request);



    expect(true)->toBeTrue(); // Just to ensure the test runs without exceptions
});

it('throws exception if user does not have permission', function () {
    $this->mock(BaseRoleService::class, function ($mock) {
        $mock->shouldReceive('for')->never();
        $mock->shouldReceive('onRequest')->never();
    });

    $this->actingAs($this->test_user);

    $request = \Mockery::mock(RoleRequest::class);
    $request->shouldReceive('validated')->andReturn([
        'role_id' => 1,
        'affiliated' => ['entity1', 'entity2'],
        'assigned' => ['criteria1', 'criteria2'],
        'name' => 'New Role Name'
    ]);

    $action = app(ManageOnRequestRoleAction::class);

    expect(fn() => $action->execute($request))->toThrow(\Symfony\Component\HttpKernel\Exception\HttpException::class);
});
