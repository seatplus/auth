<?php

namespace Seatplus\Auth\Http\Actions\Roles;

use Seatplus\Auth\Models\User;
use Seatplus\Auth\Services\Roles\AbstractRoleService;
use Seatplus\Auth\Services\Roles\BaseRoleService;
use Seatplus\Auth\Services\Roles\ManualRoleService;
use Seatplus\Auth\Services\Roles\OnRequestRoleService;

class SetModeratorAction
{
    public function __construct(
        private BaseRoleService $baseRoleService
    ) {
    }
    public function execute(int $role_id, int $user_id, bool $can_moderate): void
    {
        $this->baseRoleService->for($role_id);
        $this->checkPermission();

        /** @var OnRequestRoleService|ManualRoleService $roleService */
        $roleService = $this->baseRoleService->getTypeService();

        $this->validateRoleType($roleService);

        /** @var User $user */
        $user = User::query()->findOrFail($user_id);

        $roleService->setModerator($user, $can_moderate);
    }

    private function checkPermission(): void
    {
        /* @var User $user */
        $user = auth()->user();

        $can_moderate = $this->baseRoleService->canModerate($user);

        if (! $can_moderate) {
            abort(403, 'You are not allowed to add moderators');
        }

    }

    private function validateRoleType(AbstractRoleService $roleService): void
    {
        if (! $roleService instanceof ManualRoleService && ! $roleService instanceof OnRequestRoleService) {
            abort(403, 'This action is not allowed');
        }
    }
}
