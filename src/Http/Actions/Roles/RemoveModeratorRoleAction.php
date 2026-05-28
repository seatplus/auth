<?php

namespace Seatplus\Auth\Http\Actions\Roles;

class RemoveModeratorRoleAction
{
    public function __construct(
        private readonly SetModeratorAction $action
    ) {}

    /**
     * @throws \Throwable
     */
    public function execute(int $role_id, int $user_id): void
    {
        $this->action->execute($role_id, $user_id, false);
    }
}
