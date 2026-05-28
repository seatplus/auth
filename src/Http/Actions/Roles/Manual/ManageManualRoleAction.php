<?php

declare(strict_types=1);

namespace Seatplus\Auth\Http\Actions\Roles\Manual;

use Illuminate\Support\Arr;
use Seatplus\Auth\Enums\RoleType;
use Seatplus\Auth\Http\Requests\RoleRequest;
use Seatplus\Auth\Services\Roles\BaseRoleService;
use Seatplus\Auth\Services\Roles\DTO\AffiliationData;

class ManageManualRoleAction
{
    public function __construct(
        protected BaseRoleService $baseRoleService
    ) {}

    /**
     * @throws \Throwable
     */
    public function execute(RoleRequest $request): void
    {
        $validated = $request->validated();
        $roleService = $this->baseRoleService->for($validated['role_id'])->manual();

        $roleService->setRoleType(RoleType::MANUAL);

        if ($name = Arr::get($validated, 'name')) {
            $roleService->updateRoleName($name);
        }

        if (is_array($affiliated = Arr::get($validated, 'affiliated'))) {
            $roleService->syncAffiliateManyEntities(
                ...array_map(AffiliationData::fromArray(...), $affiliated)
            );
        }

        $roleService->handleMembers();
    }
}
