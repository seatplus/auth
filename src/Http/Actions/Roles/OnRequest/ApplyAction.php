<?php

declare(strict_types=1);

namespace Seatplus\Auth\Http\Actions\Roles\OnRequest;

use Seatplus\Auth\Models\User;
use Seatplus\Auth\Services\Roles\BaseRoleService;

class ApplyAction
{
    public function __construct(
        protected BaseRoleService $baseRoleService
    ) {}

    /**
     * @throws \Throwable
     */
    public function execute(int $role_id, int $user_id): void
    {
        $roleService = $this->baseRoleService->for($role_id)->onRequest();

        /** @var User $user */
        $user = User::query()->findOrFail($user_id);

        $roleService->submitApplicationForRole($user);
    }
}
