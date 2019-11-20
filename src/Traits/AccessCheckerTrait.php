<?php

namespace Seatplus\Auth\Traits;

use Spatie\Permission\Models\Role;

trait AccessCheckerTrait
{
    //TODO: as this trait is not being reused maybe include in original user model
    public function hasAccessTo($permission, int $id) : bool
    {
        // start by asserting if id to check belongs to the users characters
        if (in_array($id, $this->characters->pluck('character_id')->toArray())) {
            return true;
        }

        // start by asserting that the user has the required permission and id is set
        if (!$this->hasPermissionTo($permission)) {
            return false;
        }

        /*
        * If we think about corporation roles such as director or accountant we should check directPermissions
        * TODO: create direct permissions to users within CharacterRoles job
        * TODO: if refreshtoken is revoked remove all direct permissions
        */

        // Check a users roles and only take the ones into account which have a certain permission
        $users_roles_with_permission = $this->roles->filter(function (Role $role) use ($permission) {
            return $role->hasPermissionTo($permission);
        });

        // Check if role has an affiliation to the requested resource
        $users_roles_with_permission_and_affiliation = $users_roles_with_permission->isNotEmpty()
            ? $users_roles_with_permission->filter(function ($role) use ($id) {

                /*
                 * First we assume that there is always just one route parameter to check:
                 * either character_id, corporation_id or alliance_id is supplied. As a result
                 * the first id is taken from the parameters array and converted to an integer.
                 */

                if (is_int($id)) {
                    return $role->isAffiliated($id);
                }

                return false;
            })
            : collect();

        // if a role has both permission and affiliation let user see the page
        return $users_roles_with_permission_and_affiliation->isNotEmpty();
    }
}
