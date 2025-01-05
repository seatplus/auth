<?php

namespace Seatplus\Auth\Services\Permissions;

use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Models\User;
use Seatplus\Eveapi\Models\Character\CharacterInfo;

class UserPermissionService
{
    private array $corporation_roles = [];

    private array $permissions = [];

    private array $character_ids = [];

    public function __construct(
        private ?RolePermissionObjectService $role_permission_object_service = null
    ) {
        $this->role_permission_object_service = $role_permission_object_service ?? new RolePermissionObjectService;
    }

    public function get(User $user): array
    {

        $user = $user->loadMissing(['characters.roles', 'roles.permissions']);

        $this->buildCorporationRoles($user);
        $this->buildPermissions($user);
        $this->buildCharacterIds($user);

        return [
            'corporation_roles' => $this->corporation_roles,
            'permissions' => $this->permissions,
            'character_ids' => $this->character_ids,
            'owned_character_ids' => $user->characters->pluck('character_id')->toArray(),
        ];

    }

    private function buildCorporationRoles(User $user): void
    {
        $user
            ->characters
            ->each(function (CharacterInfo $character) {

                /** @var array $roles */
                $roles = $character->roles->roles ?? [];

                if (empty($roles)) {
                    return;
                }

                foreach ($roles as $role) {
                    $this->corporation_roles[$role] = array_merge($this->corporation_roles[$role] ?? [], [$character->corporation_id]);
                }
            });
    }

    private function buildPermissions(User $user): void
    {
        $user->roles->each(function (Role $role) {
            $role_permissions = $this->role_permission_object_service->get($role);

            // merge on permissions. The key might exist, so we extend the array
            $this->permissions = $role_permissions
                ->mergeRecursive($this->permissions)
                ->toArray();

        });
    }

    private function buildCharacterIds(User $user): void
    {
        $this->character_ids = $user->characters->pluck('character_id')->toArray();
    }
}
