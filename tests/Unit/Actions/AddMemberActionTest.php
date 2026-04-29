<?php

use Seatplus\Auth\Http\Actions\Roles\Manual\AddMemberAction;
use Seatplus\Auth\Http\Actions\Roles\Manual\SetMemberAction;

it('adds a member role', function () {
    $this->mock(SetMemberAction::class, function ($mock) {
        $mock->shouldReceive('execute')->with(1, 2, true)->once();
    });

    $action = app(AddMemberAction::class);

    $action->execute(1, 2);
});
