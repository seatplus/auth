<?php

declare(strict_types=1);

namespace Seatplus\Auth\Http\Actions\Roles\Manual;

use Seatplus\Auth\Models\User;
use Seatplus\Auth\Services\Roles\BaseRoleService;

class SetMemberAction
{
    public function __construct(
        protected BaseRoleService $baseRoleService
    ) {}

    /**
     * @throws \Throwable
     */
    public function execute(int $role_id, int $user_id, bool $is_member): void
    {
        $this->baseRoleService->for($role_id);
        $this->checkPermission();

        $roleService = $this->baseRoleService->manual();

        /** @var User $user */
        $user = User::query()->findOrFail($user_id);

        match ($is_member) {
            true => $roleService->addMember($user),
            false => $roleService->removeMember($user)
        };
    }

    private function checkPermission(): void
    {
        /* @var User $user */
        $user = auth()->user();

        $can_moderate = $this->baseRoleService->canModerate($user);

        if (! $can_moderate) {
            abort(403, 'You are not allowed to do this action');
        }

    }
}
