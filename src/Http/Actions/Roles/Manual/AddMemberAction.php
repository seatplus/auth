<?php

declare(strict_types=1);

namespace Seatplus\Auth\Http\Actions\Roles\Manual;

class AddMemberAction
{
    public function __construct(
        private SetMemberAction $setMember
    ) {}

    /**
     * @throws \Throwable
     */
    public function execute(int $role_id, int $user_id): void
    {
        $this->setMember->execute($role_id, $user_id, true);
    }
}
