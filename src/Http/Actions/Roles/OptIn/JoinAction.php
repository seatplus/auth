<?php

declare(strict_types=1);

namespace Seatplus\Auth\Http\Actions\Roles\OptIn;

use Seatplus\Auth\Models\User;
use Seatplus\Auth\Services\Roles\BaseRoleService;

class JoinAction
{
    public function __construct(
        private ?BaseRoleService $baseRoleService = null
    ) {
        $this->baseRoleService = $baseRoleService ?? new BaseRoleService;
    }

    /**
     * @throws \Throwable
     */
    public function execute(int $role_id, int $user_id): void
    {
        $roleService = $this->baseRoleService->for($role_id)->optIn();

        /** @var User $user */
        $user = User::query()->findOrFail($user_id);

        $roleService->joinRole($user);
    }
}
