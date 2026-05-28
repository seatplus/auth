<?php

namespace Seatplus\Auth\Http\Actions\Roles\Manual;

class RemoveMemberAction
{
    public function __construct(
        private readonly SetMemberAction $setMember
    ) {}

    /**
     * @throws \Throwable
     */
    public function execute(int $role_id, int $user_id): void
    {
        $this->setMember->execute($role_id, $user_id, false);
    }
}
