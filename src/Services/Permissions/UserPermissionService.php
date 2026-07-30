<?php

declare(strict_types=1);

namespace Seatplus\Auth\Services\Permissions;

use Seatplus\Auth\Models\Permissions\Permission;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Models\User;
use Seatplus\Eveapi\Models\Character\CharacterInfo;

class UserPermissionService
{
    private array $corporationRoles = [];

    private array $permissionRoles = [];

    public function get(User $user): array
    {

        // `characters.characterAffiliation` is eager-loaded because buildCorporationRoles() reads
        // each character's `corporation_id` accessor, which resolves the characterAffiliation
        // relation — without this it lazy-loads and throws under Model::preventLazyLoading().
        $user = $user->loadMissing(['characters.roles', 'characters.characterAffiliation', 'roles.permissions']);

        $this->buildCorporationRoles($user);
        $this->buildPermissionRoles($user);

        return [
            'corporation_roles' => $this->corporationRoles,
            'permission_roles' => $this->permissionRoles,
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

    private function buildPermissionRoles(User $user): void
    {
        // record which of the user's roles grant each permission, so CanUserService can resolve
        // affiliation live per permission via AffiliationResolver — no materialised id arrays.
        $user->roles->each(function (Role $role) {
            $role->permissions->each(function (Permission $permission) use ($role) {
                $this->permissionRoles[$permission->name][] = $role->id;
            });
        });
    }
}
