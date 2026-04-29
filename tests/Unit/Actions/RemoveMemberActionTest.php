<?php

use Seatplus\Auth\Http\Actions\Roles\Manual\RemoveMemberAction;
use Seatplus\Auth\Http\Actions\Roles\Manual\SetMemberAction;
use Seatplus\Auth\Models\Permissions\Role;

it('removes a member from a role', function () {
    $role = Role::create(['name' => 'test']);

    $this->mock(SetMemberAction::class, function ($mock) use ($role) {
        $mock->shouldReceive('execute')->with($role->id, 1, false)->once();
    });

    $action = app(RemoveMemberAction::class);
    $action->execute($role->id, 1);
});
