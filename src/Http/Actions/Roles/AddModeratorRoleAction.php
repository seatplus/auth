<?php

declare(strict_types=1);

namespace Seatplus\Auth\Http\Actions\Roles;

class AddModeratorRoleAction
{
    public function __construct(
        private readonly SetModeratorAction $setModerator
    ) {}

    /**
     * @throws \Throwable
     */
    public function execute(int $role_id, int $user_id): void
    {
        $this->setModerator->execute($role_id, $user_id, true);
    }
}
