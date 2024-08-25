<?php

namespace Seatplus\Auth\Http\Actions\Roles;

use Illuminate\Support\Arr;
use Seatplus\Auth\Enums\RoleType;
use Seatplus\Auth\Http\Requests\RoleRequest;
use Seatplus\Auth\Services\Roles\BaseRoleService;

class ManageAutomaticRoleAction
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
        $this->checkPermission();

        $validated = $request->validated();
        $this->baseRoleService->for($validated['role_id']);

        $roleService = $this->baseRoleService->automatic();

        if ($name = Arr::get($validated, 'name')) {
            $roleService->updateRoleName($name);
        }

        if ($affiliated = Arr::get($validated, 'affiliated')) {
            $roleService->syncAffiliateManyEntities($affiliated);
        }

        if ($assigned = Arr::get($validated, 'assigned')) {
            $roleService->automaticallyAssignRoleTo($assigned);
        }

        $roleService->setRoleType(RoleType::AUTOMATIC);
    }

    private function checkPermission()
    {

        $auth = auth()->user();

        throw_unless($auth, \Exception::class, 'User not authenticated');

        if (! auth()->user()->can('administrate access control groups')) {
            abort(403, 'You are not allowed to administrate access control groups');
        }
    }
}
