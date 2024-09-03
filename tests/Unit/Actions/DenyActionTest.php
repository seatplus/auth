<?php
use Seatplus\Auth\Http\Actions\Roles\OnRequest\DenyAction;
use Seatplus\Auth\Services\Roles\BaseRoleService;
use Seatplus\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Database\Eloquent\ModelNotFoundException;

it('denies role application for user successfully', function () {
    $this->mock(BaseRoleService::class, function ($mock) {
        $mock->shouldReceive('for')->with(1)->andReturnSelf();
        $mock->shouldReceive('onRequest')->andReturn(mock(\Seatplus\Auth\Services\Roles\OnRequestRoleService::class, function ($mock) {
            $mock->shouldReceive('denyApplication')->once();
        }));
    });

    $user = User::factory()->create();

    $action = app(DenyAction::class);
    $action->execute(1, $user->id);
});

it('throws exception if user not found during application', function () {
    $this->mock(BaseRoleService::class, function ($mock) {
        $mock->shouldReceive('for')->with(1)->andReturnSelf();
        $mock->shouldReceive('onRequest')->andReturn(mock(\Seatplus\Auth\Services\Roles\OnRequestRoleService::class, function ($mock) {
            $mock->shouldReceive('submitApplicationForRole')->never();
        }));
    });

    $action = app(DenyAction::class);

    expect(fn() => $action->execute(1, 999))->toThrow(ModelNotFoundException::class);
});
