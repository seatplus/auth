<?php

namespace Seatplus\Auth\Services\Permissions;

use Closure;
use Illuminate\Pipeline\Pipeline;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Seatplus\Auth\Models\User;
use Seatplus\Auth\Services\Permissions\DTO\ValidateIdsDTO;

class CanUserService
{
    public function __construct(
        private ?UserPermissionService $user_permission_service = null
    ) {
        $this->user_permission_service ??= new UserPermissionService;
    }

    /**
     * @throws ValidationException
     */
    public function check(User $user, ValidateIdsDTO $idsDTO, array $permissions, array $corporation_roles = []): bool
    {
        $ids_to_validate = $idsDTO->get();

        // match whether ids are provided or not
        $is_validated = match (empty($ids_to_validate)) {
            true => $this->validateSimplePermissions($user, $permissions, $corporation_roles),
            false => $this->validateIds($user, $ids_to_validate, $permissions, $corporation_roles)
        };

        return $is_validated || $user->can('superuser');
    }

    private function validateOwnedCharacterIds(array $data, Closure $next): array
    {
        $ids_to_validate = $data['ids_to_validate'];

        $owned_character_ids = $data['user_permissions']['owned_character_ids'];

        // remove owned character ids from ids
        $ids_to_validate = array_diff($ids_to_validate, $owned_character_ids);

        $data['ids_to_validate'] = $ids_to_validate;

        return $next($data);
    }

    private function validateCorporationRoles(array $data, Closure $next): array
    {
        $ids_to_validate = $data['ids_to_validate'];

        // if no ids are left, we return early
        if (empty($ids_to_validate)) {
            return $next($data);
        }

        $corporation_roles = $data['corporation_roles'];
        $user_permissions = $data['user_permissions'];

        // if a corporation role is provided, we check if the user has the required role
        if ($corporation_roles) {

            // add Director to corporation roles
            $corporation_roles[] = 'Director';

            foreach ($corporation_roles as $corporation_role) {
                $corporation_role_ids = $user_permissions['corporation_roles'][$corporation_role] ?? [];

                // remove ids that are within the corp_ids from ids_to_validate
                $ids_to_validate = array_diff($ids_to_validate, $corporation_role_ids);
                $data['ids_to_validate'] = $ids_to_validate;

                // if ids are empty, end the loop
                if (empty($ids_to_validate)) {
                    break;
                }
            }
        }

        return $next($data);
    }

    private function validatePermissions(array $data, Closure $next): array
    {
        $ids_to_validate = $data['ids_to_validate'];

        // if no ids are left, we return early
        if (empty($ids_to_validate)) {
            return $next($data);
        }

        $permissions = $data['permissions'];
        $user_permissions = $data['user_permissions'];

        // check if user has the required permissions
        foreach ($permissions as $permission) {
            $ids_with_permission = $user_permissions['permissions'][$permission] ?? [];

            // remove ids that are within the ids_with_permission from ids_to_validate
            $ids_to_validate = array_diff($ids_to_validate, $ids_with_permission);
            $data['ids_to_validate'] = $ids_to_validate;

            // if ids are empty, end the loop
            if (empty($ids_to_validate)) {
                break;
            }
        }

        return $next($data);
    }

    private function validateIds(User $user, array $ids_to_validate, array $permissions, array $corporation_roles): bool
    {

        $data = app(Pipeline::class)
            ->send([
                'ids_to_validate' => $ids_to_validate,
                'user_permissions' => $this->getUserPermissionObject($user),
                'permissions' => $permissions,
                'corporation_roles' => $corporation_roles,
            ])
            ->through([
                fn (array $data, Closure $next) => $this->validateOwnedCharacterIds($data, $next),
                fn (array $data, Closure $next) => $this->validateCorporationRoles($data, $next),
                fn (array $data, Closure $next) => $this->validatePermissions($data, $next),
            ])->thenReturn();

        $ids_not_validated = $data['ids_to_validate'];

        // return true if all ids are validated
        return empty($ids_not_validated);
    }

    private function validateSimplePermissions(User $user, array $permissions, array $corporation_role): bool
    {
        if ($user->hasAnyPermission($permissions)) {
            return true;
        }

        $user_permission_object = $this->getUserPermissionObject($user);

        $users_corporation_roles = array_keys([...$user_permission_object['corporation_roles']]);

        // if user corporation roles contain the role 'Director' we return true
        if (in_array('Director', $users_corporation_roles)) {
            return true;
        }

        // if any of the corporation roles is in the users corporation roles, we return true
        return (bool) array_intersect($corporation_role, $users_corporation_roles);
    }

    public function getUserPermissionObject(User $user): mixed
    {
        return Cache::remember("user_permissions_{$user->id}", now()->addMinutes(5), fn () => $this->user_permission_service->get($user));
    }
}
