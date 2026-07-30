<?php

declare(strict_types=1);

namespace Seatplus\Auth\Services\Roles;

use Seatplus\Auth\Models\Permissions\Role;

/**
 * Thin compatibility shim over {@see AffiliationResolver}. The set is resolved live in SQL rather
 * than by materialising whole entity tables into PHP; the returned conflated `array` shape is
 * unchanged, so existing consumers (UserPermissionService, web's GetAffiliatedIds) are unaffected.
 */
class RoleAffiliatedIdsService
{
    /**
     * @return array<int, int>
     */
    public static function get(Role $role): array
    {
        return (new AffiliationResolver)->resolve([$role->id]);
    }
}
