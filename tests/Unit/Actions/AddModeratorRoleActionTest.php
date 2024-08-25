<?php

namespace Seatplus\Auth\Tests\Unit\Actions;

use Seatplus\Auth\Http\Actions\Roles\SetModeratorAction;

it('adds a moderator role', function () {
    $this->mock(SetModeratorAction::class, function ($mock) {
        $mock->shouldReceive('execute')->with(1, 2, true)->once();
    });

    $action = app(\Seatplus\Auth\Http\Actions\Roles\AddModeratorRoleAction::class);

    $action->execute(1, 2);
});
