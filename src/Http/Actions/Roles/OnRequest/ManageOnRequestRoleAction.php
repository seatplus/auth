<?php

declare(strict_types=1);

namespace Seatplus\Auth\Http\Actions\Roles\OnRequest;

use Illuminate\Support\Arr;
use Seatplus\Auth\Enums\RoleType;
use Seatplus\Auth\Http\Requests\RoleRequest;
use Seatplus\Auth\Services\Roles\BaseRoleService;
use Seatplus\Auth\Services\Roles\DTO\AffiliationData;
use Seatplus\Auth\Services\Roles\DTO\CriteriaData;

class ManageOnRequestRoleAction
{
    public function __construct(
        protected BaseRoleService $baseRoleService
    ) {}

    /**
     * @throws \Throwable
     */
    public function execute(RoleRequest $request): void
    {
        $this->checkPermission();

        $validated = $request->validated();
        $roleService = $this->baseRoleService->for($validated['role_id'])->onRequest();

        $roleService->setRoleType(RoleType::ON_REQUEST);

        if ($name = Arr::get($validated, 'name')) {
            $roleService->updateRoleName($name);
        }

        if (is_array($affiliated = Arr::get($validated, 'affiliated'))) {
            $roleService->syncAffiliateManyEntities(
                ...array_map(AffiliationData::fromArray(...), $affiliated)
            );
        }

        if (is_array($assigned = Arr::get($validated, 'assigned'))) {
            $roleService->addCriteriaForRoleApplication(
                ...array_map(CriteriaData::fromArray(...), $assigned)
            );
        }

        $roleService->handleMembers();
    }

    private function checkPermission(): void
    {
        if (! auth()->user()->can('administrate access control groups')) {
            abort(403, 'You are not allowed to administrate access control groups');
        }
    }
}
