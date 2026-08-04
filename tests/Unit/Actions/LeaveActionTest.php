<?php

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Seatplus\Auth\Http\Actions\Roles\OptIn\LeaveAction;
use Seatplus\Auth\Models\User;
use Seatplus\Auth\Services\Roles\BaseRoleService;
use Seatplus\Auth\Services\Roles\OptInRoleService;

it('executes leave action successfully', function () {
    $this->mock(BaseRoleService::class, function ($mock) {
        $mock->shouldReceive('for')->with(1)->andReturnSelf();
        $mock->shouldReceive('optIn')->andReturn(mock(OptInRoleService::class, function ($mock) {
            $mock->shouldReceive('leaveRole')->once();
        }));
    });

    $user = User::factory()->create();

    $action = app(LeaveAction::class);
    $action->execute(1, $user->id);
});

it('throws exception if user not found during leave', function () {
    $this->mock(BaseRoleService::class, function ($mock) {
        $mock->shouldReceive('for')->with(1)->andReturnSelf();
        $mock->shouldReceive('optIn')->andReturn(mock(OptInRoleService::class, function ($mock) {
            $mock->shouldReceive('leaveRole')->never();
        }));
    });

    $action = app(LeaveAction::class);

    expect(fn () => $action->execute(1, 999))->toThrow(ModelNotFoundException::class);
});
