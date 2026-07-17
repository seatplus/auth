<?php

declare(strict_types=1);

namespace Seatplus\Auth\Services\Permissions;

use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Models\User;
use Seatplus\Eveapi\Models\Character\CharacterInfo;

class UserPermissionService
{
    private array $corporationRoles = [];

    private array $permissions = [];

    private array $characterIds = [];

    public function __construct(
        private readonly RolePermissionObjectService $rolePermissionObjectService = new RolePermissionObjectService,
    ) {}

    public function get(User $user): array
    {

        // `characters.characterAffiliation` is eager-loaded because buildCorporationRoles() reads
        // each character's `corporation_id` accessor, which resolves the characterAffiliation
        // relation — without this it lazy-loads and throws under Model::preventLazyLoading().
        $user = $user->loadMissing(['characters.roles', 'characters.characterAffiliation', 'roles.permissions']);

        $this->buildCorporationRoles($user);
        $this->buildPermissions($user);
        $this->buildCharacterIds($user);

        return [
            'corporation_roles' => $this->corporationRoles,
            'permissions' => $this->permissions,
            'character_ids' => $this->characterIds,
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
                    $this->corporationRoles[$role] = array_merge($this->corporationRoles[$role] ?? [], [$character->corporation_id]);
                }
            });
    }

    private function buildPermissions(User $user): void
    {
        $user->roles->each(function (Role $role) {
            $role_permissions = $this->rolePermissionObjectService->get($role);

            // merge on permissions. The key might exist, so we extend the array
            $this->permissions = $role_permissions
                ->mergeRecursive($this->permissions)
                ->toArray();

        });
    }

    private function buildCharacterIds(User $user): void
    {
        $this->characterIds = $user->characters->pluck('character_id')->toArray();
    }
}
