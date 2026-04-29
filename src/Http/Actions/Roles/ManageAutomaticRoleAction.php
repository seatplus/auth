<?php

declare(strict_types=1);

namespace Seatplus\Auth\Http\Actions\Roles;

use Illuminate\Support\Arr;
use Seatplus\Auth\Enums\RoleType;
use Seatplus\Auth\Http\Requests\RoleRequest;
use Seatplus\Auth\Services\Roles\BaseRoleService;
use Seatplus\Auth\Services\Roles\DTO\AffiliationData;
use Seatplus\Auth\Services\Roles\DTO\CriteriaData;

class ManageAutomaticRoleAction
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
        $roleService = $this->baseRoleService->for($validated['role_id'])->automatic();

        // setRoleType first: if the type changes it calls resetRoleMemberships(),
        // which would wipe any criteria written below.
        $roleService->setRoleType(RoleType::AUTOMATIC);

        if ($name = Arr::get($validated, 'name')) {
            $roleService->updateRoleName($name);
        }

        if ($affiliated = Arr::get($validated, 'affiliated')) {
            $roleService->syncAffiliateManyEntities(
                ...array_map(fn (array $affiliationData) => AffiliationData::fromArray($affiliationData), $affiliated)
            );
        }

        if ($assigned = Arr::get($validated, 'assigned')) {
            $roleService->automaticallyAssignRoleTo(
                ...array_map(fn (array $criteriaData) => CriteriaData::fromArray($criteriaData), $assigned)
            );
        }

        $roleService->handleMembers();
    }

    private function checkPermission(): void
    {
        $auth = auth()->user();

        throw_unless($auth, \Exception::class, 'User not authenticated');

        if (! auth()->user()->can('administrate access control groups')) {
            abort(403, 'You are not allowed to administrate access control groups');
        }
    }
}
