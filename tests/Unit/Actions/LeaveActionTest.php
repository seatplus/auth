<?php

use Illuminate\Database\Eloquent\ModelNotFoundException;
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

    $action = app(\Seatplus\Auth\Http\Actions\Roles\OptIn\LeaveAction::class);
    $action->execute(1, $user->id);

    expect(true)->toBeTrue(); // Just to ensure the test runs without exceptions
});

it('throws exception if user not found during leave', function () {
    $this->mock(BaseRoleService::class, function ($mock) {
        $mock->shouldReceive('for')->with(1)->andReturnSelf();
        $mock->shouldReceive('optIn')->andReturn(mock(OptInRoleService::class, function ($mock) {
            $mock->shouldReceive('leaveRole')->never();
        }));
    });

    $action = app(\Seatplus\Auth\Http\Actions\Roles\OptIn\LeaveAction::class);

    expect(fn() => $action->execute(1, 999))->toThrow(ModelNotFoundException::class);
});
