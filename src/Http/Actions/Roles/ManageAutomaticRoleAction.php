<?php

namespace Seatplus\Auth\Http\Actions\Roles;

use Seatplus\Auth\Http\Requests\RoleRequest;
use Seatplus\Auth\Services\Roles\AutomaticRoleService;
use Seatplus\Auth\Services\Roles\BaseRoleService;

class ManageAutomaticRoleAction
{
    private AutomaticRoleService $roleService;

    public function __construct(
        private ?BaseRoleService $baseRoleService = null
    ) {
        $this->baseRoleService = $baseRoleService ?? new BaseRoleService;
    }

    /**
     * @throws \Throwable
     */
    public function __invoke(RoleRequest $request): void
    {
        $validated = $request->validated();

        // tell the role service which role we are working with
        $this->baseRoleService->for($validated['role_id']);

        $this->roleService = $this->baseRoleService->automatic();

        // if affiliated entities are provided, we affiliate them
        if ($validated['affiliated']) {
            $this->roleService->syncAffiliateManyEntities($validated['affiliated']);
        }

        // if entities are assigned, we assign them
        if ($validated['assigned']) {
            $this->assignEntities($validated['assigned']);
        }
    }

    private function assignEntities(array $entities): void
    {

        $corporation_ids = collect($entities)
            ->filter(fn ($entity) => $entity['entity_type'] === 'corporation')
            ->pluck('entity_id')
            ->toArray();

        $alliance_ids = collect($entities)
            ->filter(fn ($entity) => $entity['entity_type'] === 'alliance')
            ->pluck('entity_id')
            ->toArray();

        $this->roleService->automaticallyAssignRoleTo($corporation_ids, $alliance_ids);
    }
}
