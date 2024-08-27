<?php

it('removes a member from a role', function () {
    $role = \Seatplus\Auth\Models\Permissions\Role::create(['name' => 'test']);

    $this->mock(\Seatplus\Auth\Http\Actions\Roles\Manual\SetMemberAction::class, function ($mock) use ($role) {
        $mock->shouldReceive('execute')->with($role->id, 1, false)->once();
    });

    $action = app(\Seatplus\Auth\Http\Actions\Roles\Manual\RemoveMemberAction::class);
    $action->execute($role->id, 1);
});
