<?php

use Mockery\MockInterface;
use Seatplus\Auth\Http\Actions\Roles\SetModeratorAction;
use Seatplus\Auth\Services\Roles\AutomaticRoleService;
use Seatplus\Auth\Services\Roles\BaseRoleService;
use Seatplus\Auth\Services\Roles\OnRequestRoleService;

it('throws exception cannot moderate', function () {
    $this->actingAs($this->test_user);

    $this->mock(BaseRoleService::class, function (MockInterface $mock) {
        $mock->shouldReceive('for')->with(1);
        $mock->shouldReceive('canModerate')->andReturn(false);
    });

    $action = app(SetModeratorAction::class);

    $action->execute(1, 1, true);

})->throws(Exception::class, 'You are not allowed to add moderators');

it('sets moderator role', function () {
    $this->actingAs($this->test_user);

    $this->mock(BaseRoleService::class, function (MockInterface $mock) {
        $mock->shouldReceive('for')->with(1);
        $mock->shouldReceive('canModerate')->andReturn(true);
        $mock->shouldReceive('getTypeService')->andReturn(
            mock(OnRequestRoleService::class, function (MockInterface $mock) {
                $mock->shouldReceive('setModerator')->once()->andReturn();
            })
        );
    });

    $action = app(SetModeratorAction::class);

    $action->execute(1, $this->test_user->id, true);
});

it('throws exception if role type is not manual or on request', function () {
    $this->actingAs($this->test_user);

    $this->mock(BaseRoleService::class, function (MockInterface $mock) {
        $mock->shouldReceive('for')->with(1);
        $mock->shouldReceive('canModerate')->andReturn(true);
        $mock->shouldReceive('getTypeService')->andReturn(
            mock(AutomaticRoleService::class)
        );
    });

    $action = app(SetModeratorAction::class);

    $action->execute(1, 1, true);
})->throws(Exception::class, 'This action is not allowed');
