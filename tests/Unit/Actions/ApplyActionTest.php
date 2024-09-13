<?php

use Illuminate\Database\Eloquent\ModelNotFoundException;
use Seatplus\Auth\Http\Actions\Roles\OnRequest\ApplyAction;
use Seatplus\Auth\Services\Roles\BaseRoleService;

it('applies role to user successfully', function () {
    $this->mock(BaseRoleService::class, function (\Mockery\MockInterface $mock) {
        $mock->shouldReceive('for')->with(1)
            ->andReturnSelf();

        $mock->shouldReceive('onRequest')
            ->once()
            ->andReturn(mock(\Seatplus\Auth\Services\Roles\OnRequestRoleService::class, function (\Mockery\MockInterface $mock) {
                $mock->shouldReceive('onRequest')->andReturnSelf();
                $mock->shouldReceive('submitApplicationForRole')->once();
            }));
    });

    $user = $this->test_user;

    $action = app(ApplyAction::class);

    $action->execute(1, $user->id);

    //expect(true)->toBeTrue(); // Just to ensure the test runs without exceptions
});

it('throws exception if user not found', function () {
    $this->mock(BaseRoleService::class, function (\Mockery\MockInterface $mock) {
        $mock->shouldReceive('for')->with(1)
            ->andReturnSelf();

        $mock->shouldReceive('onRequest')
            ->once()
            ->andReturn(mock(\Seatplus\Auth\Services\Roles\OnRequestRoleService::class, function (\Mockery\MockInterface $mock) {
                $mock->shouldReceive('onRequest')->andReturnSelf();
                $mock->shouldReceive('submitApplicationForRole')->never();
            }));
    });

    $action = app(ApplyAction::class);

    expect(fn () => $action->execute(1, 999))->toThrow(ModelNotFoundException::class);
});
