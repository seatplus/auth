<?php

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Seatplus\Auth\Http\Actions\Roles\OptIn\JoinAction;
use Seatplus\Auth\Models\User;
use Seatplus\Auth\Services\Roles\BaseRoleService;
use Seatplus\Auth\Services\Roles\OptInRoleService;

it('executes join action successfully', function () {
    $this->mock(BaseRoleService::class, function ($mock) {
        $mock->shouldReceive('for')->with(1)->andReturnSelf();
        $mock->shouldReceive('optIn')->andReturn(mock(OptInRoleService::class, function ($mock) {
            $mock->shouldReceive('joinRole')->once();
        }));
    });

    $user = User::factory()->create();

    $action = app(JoinAction::class);
    $action->execute(1, $user->id);

    expect(true)->toBeTrue(); // Just to ensure the test runs without exceptions
});

it('throws exception if user not found during join', function () {
    $this->mock(BaseRoleService::class, function ($mock) {
        $mock->shouldReceive('for')->with(1)->andReturnSelf();
        $mock->shouldReceive('optIn')->andReturn(mock(OptInRoleService::class, function ($mock) {
            $mock->shouldReceive('joinRole')->never();
        }));
    });

    $action = app(JoinAction::class);

    expect(fn () => $action->execute(1, 999))->toThrow(ModelNotFoundException::class);
});

it('throws exception if role service not found during join', function () {
    $this->mock(BaseRoleService::class, function ($mock) {
        $mock->shouldReceive('for')->with(1)->andThrow(new Exception('Role service not found'));
    });

    $user = User::factory()->create();

    $action = app(JoinAction::class);

    expect(fn () => $action->execute(1, $user->id))->toThrow(Exception::class);
});
