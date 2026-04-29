<?php

use Mockery\MockInterface;
use Seatplus\Auth\Http\Actions\Roles\Manual\SetMemberAction;
use Seatplus\Auth\Services\Roles\BaseRoleService;
use Seatplus\Auth\Services\Roles\ManualRoleService;

it('throws exception if user cannot moderate', function () {

    $this->mock(BaseRoleService::class, function (MockInterface $mock) {

        $mock->shouldReceive('for')
            ->once()
            ->with(1);

        $mock->shouldReceive('canModerate')
            ->once()
            ->andReturn(false);
    });

    $action = app(SetMemberAction::class);

    $this->actingAs($this->test_user);
    $action->execute(1, 1, true);
})->throws(Exception::class, 'You are not allowed to do this action');

it('sets member', function (bool $is_member) {

    $this->mock(BaseRoleService::class, function (MockInterface $mock) use ($is_member) {

        $mock->shouldReceive('for')
            ->once()
            ->with(1);

        $mock->shouldReceive('canModerate')
            ->once()
            ->andReturn(true);

        $mock->shouldReceive('manual')
            ->once()
            ->andReturn(mock(ManualRoleService::class, function (MockInterface $mock) use ($is_member) {

                if ($is_member) {
                    $mock->shouldReceive('addMember')
                        ->once();
                } else {
                    $mock->shouldReceive('removeMember')
                        ->once();
                }

            }));
    });

    $action = app(SetMemberAction::class);

    $this->actingAs($this->test_user);
    $action->execute(1, $this->test_user->id, $is_member);
})->with([true, false]);
