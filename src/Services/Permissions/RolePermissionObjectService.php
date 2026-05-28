<?php

declare(strict_types=1);

namespace Seatplus\Auth\Services\Permissions;

use Illuminate\Support\Collection;
use Seatplus\Auth\Models\Permissions\Permission;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Services\Roles\RoleAffiliatedIdsService;

class RolePermissionObjectService
{
    public function __construct(
        private readonly RoleAffiliatedIdsService $roleAffiliatedIdsService = new RoleAffiliatedIdsService,
    ) {}

    public function get(Role $role): Collection
    {
        $role = $role->loadMissing('permissions');

        $affiliated_ids = $this->roleAffiliatedIdsService->get($role);

        return $role->permissions
            ->mapWithKeys(fn (Permission $permission) => [$permission->name => $affiliated_ids]);
    }
}
