<?php

namespace Seatplus\Auth\Http\Actions\Roles\Manual;

use Illuminate\Support\Arr;
use Seatplus\Auth\Enums\RoleType;
use Seatplus\Auth\Http\Requests\RoleRequest;
use Seatplus\Auth\Services\Roles\BaseRoleService;

class ManageManualRoleAction
{
    public function __construct(
        protected BaseRoleService $baseRoleService
    ) {
    }

    /**
     * @throws \Throwable
     */
    public function execute(RoleRequest $request): void
    {
        $validated = $request->validated();
        $roleService = $this->baseRoleService->for($validated['role_id'])->manual();

        if ($affiliated = Arr::get($validated, 'affiliated')) {
            $roleService->syncAffiliateManyEntities($affiliated);
        }

        if ($name = Arr::get($validated, 'name')) {
            $roleService->updateRoleName($name);
        }

        $roleService->setRoleType(RoleType::MANUAL);
    }
}
