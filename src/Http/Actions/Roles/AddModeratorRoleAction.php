<?php

namespace Seatplus\Auth\Http\Actions\Roles;

use Seatplus\Auth\Http\Requests\RoleRequest;
use Seatplus\Auth\Models\User;
use Seatplus\Auth\Services\Roles\AutomaticRoleService;
use Seatplus\Auth\Services\Roles\BaseRoleService;
use Seatplus\Auth\Services\Roles\ManualRoleService;
use Seatplus\Auth\Services\Roles\OnRequestRoleService;

class AddModeratorRoleAction
{

    public function __construct(
        private readonly SetModeratorAction $setModerator
    ) {
    }

    /**
     * @throws \Throwable
     */
    public function execute(int $role_id, int $user_id): void
    {
        $this->setModerator->execute($role_id, $user_id,true);
    }


}
