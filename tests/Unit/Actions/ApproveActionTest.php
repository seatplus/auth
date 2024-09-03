<?php

use Seatplus\Auth\Http\Actions\Roles\OnRequest\ApproveAction;
use Seatplus\Auth\Services\Roles\BaseRoleService;
use Seatplus\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\ModelNotFoundException;

it('approves role application for user successfully', function () {

    $this->mock(BaseRoleService::class, function (\Mockery\MockInterface $mock) {
        $mock->shouldReceive('for')->with(1)->andReturnSelf();
        $mock->shouldReceive('onRequest')->andReturn(mock(\Seatplus\Auth\Services\Roles\OnRequestRoleService::class, function (\Mockery\MockInterface $mock) {
            $mock->shouldReceive('approveApplicationForRole')->once();
        }));
    });

    $user = User::factory()->create();

    $action = app(ApproveAction::class);
    $action->execute(1, $user->id);
});

it('throws exception if user not found during approval', function () {
    $roleServiceMock = $this->mock(BaseRoleService::class, function ($mock) {
        $mock->shouldReceive('for')->with(1)->andReturnSelf();
        $mock->shouldReceive('onRequest')->andReturn(mock(\Seatplus\Auth\Services\Roles\OnRequestRoleService::class, function ($mock) {
            $mock->shouldReceive('approveApplicationForRole')->never();
        }));
    });

    $action = app(ApproveAction::class, ['baseRoleService' => $roleServiceMock]);

    expect(fn() => $action->execute(1, 999))->toThrow(ModelNotFoundException::class);
});
