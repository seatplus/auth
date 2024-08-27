<?php

it('adds a member role', function () {
    $this->mock(\Seatplus\Auth\Http\Actions\Roles\Manual\SetMemberAction::class, function ($mock) {
        $mock->shouldReceive('execute')->with(1, 2, true)->once();
    });

    $action = app(\Seatplus\Auth\Http\Actions\Roles\Manual\AddMemberAction::class);

    $action->execute(1, 2);
});
