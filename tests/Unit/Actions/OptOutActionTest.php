<?php

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Seatplus\Auth\Models\User;
use Seatplus\Auth\Services\Roles\BaseRoleService;
use Seatplus\Auth\Services\Roles\OnRequestRoleService;

it('executes opt out action successfully', function () {
    $this->mock(BaseRoleService::class, function ($mock) {
        $mock->shouldReceive('for')->with(1)->andReturnSelf();
        $mock->shouldReceive('onRequest')->andReturn(mock(OnRequestRoleService::class, function ($mock) {
            $mock->shouldReceive('removeApplication')->once();
        }));
    });

    $user = User::factory()->create();

    $action = app(\Seatplus\Auth\Http\Actions\Roles\OnRequest\OptOutAction::class);
    $action->execute(1, $user->id);

    expect(true)->toBeTrue(); // Just to ensure the test runs without exceptions
});

it('throws exception if user not found during opt out', function () {
    $this->mock(BaseRoleService::class, function ($mock) {
        $mock->shouldReceive('for')->with(1)->andReturnSelf();
        $mock->shouldReceive('onRequest')->andReturn(mock(OnRequestRoleService::class, function ($mock) {
            $mock->shouldReceive('removeApplication')->never();
        }));
    });

    $action = app(\Seatplus\Auth\Http\Actions\Roles\OnRequest\OptOutAction::class);

    expect(fn() => $action->execute(1, 999))->toThrow(ModelNotFoundException::class);
});
