<?php

use Seatplus\Auth\Http\Actions\Roles\SetModeratorAction;

it('throws exception cannot moderate', function () {
    $this->actingAs(test()->test_user);

    $this->mock(\Seatplus\Auth\Services\Roles\BaseRoleService::class, function (\Mockery\MockInterface $mock) {
        $mock->shouldReceive('for')->with(1);
        $mock->shouldReceive('canModerate')->andReturn(false);
    });

    $action = app(SetModeratorAction::class);

    $action->execute(1, 1, true);

})->throws(\Exception::class, 'You are not allowed to add moderators');

it('sets moderator role', function () {
    $this->actingAs(test()->test_user);

    $this->mock(\Seatplus\Auth\Services\Roles\BaseRoleService::class, function (\Mockery\MockInterface $mock) {
        $mock->shouldReceive('for')->with(1);
        $mock->shouldReceive('canModerate')->andReturn(true);
        $mock->shouldReceive('getTypeService')->andReturn(
            mock(\Seatplus\Auth\Services\Roles\OnRequestRoleService::class, function (\Mockery\MockInterface $mock) {
                $mock->shouldReceive('setModerator')->once()->andReturn();
            })
        );
    });

    $action = app(SetModeratorAction::class);

    $action->execute(1, test()->test_user->id, true);
});

it('throws exception if role type is not manual or on request', function () {
    $this->actingAs(test()->test_user);

    $this->mock(\Seatplus\Auth\Services\Roles\BaseRoleService::class, function (\Mockery\MockInterface $mock) {
        $mock->shouldReceive('for')->with(1);
        $mock->shouldReceive('canModerate')->andReturn(true);
        $mock->shouldReceive('getTypeService')->andReturn(
            mock(\Seatplus\Auth\Services\Roles\AutomaticRoleService::class)
        );
    });

    $action = app(SetModeratorAction::class);

    $action->execute(1, 1, true);
})->throws(\Exception::class, 'This action is not allowed');
