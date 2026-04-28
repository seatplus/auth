<?php

declare(strict_types=1);

namespace Seatplus\Auth\Http\Actions\Roles\OnRequest;

use Illuminate\Support\Arr;
use Seatplus\Auth\Enums\RoleType;
use Seatplus\Auth\Http\Requests\RoleRequest;
use Seatplus\Auth\Services\Roles\BaseRoleService;

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

        if ($affiliated = Arr::get($validated, 'affiliated')) {
            $roleService->syncAffiliateManyEntities(
                collect($affiliated)
                    ->map(fn (array $e) => [$e['entity_id'], $e['entity_type'], $e['affiliation_type']])
                    ->all()
            );
        }

        if ($assigned = Arr::get($validated, 'assigned')) {
            $roleService->addCriteriaForRoleApplication(
                collect($assigned)
                    ->map(fn (array $e) => [$e['entity_id'], $e['entity_type']])
                    ->all()
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
