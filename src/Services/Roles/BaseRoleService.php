<?php

namespace Seatplus\Auth\Services\Roles;

use Seatplus\Auth\Enums\RoleType;
use Seatplus\Auth\Models\Permissions\Role;

class BaseRoleService
{
    public function __construct(
        private ?Role $role = null
    )
    {
    }

    public static function make(Role|string|int $role): self
    {
        return (new self)->for($role);
    }

    public function for(Role|string|int $role): self
    {

        /* @var Role $role */
        $role = match (true) {
            $role instanceof Role => $role,
            is_string($role) => Role::findByName($role),
            is_int($role) => Role::findById($role),
        };

        $this->role = $role;

        return $this;
    }

    public function automatic(): AutomaticRoleService
    {
        return new AutomaticRoleService($this->role);
    }



}
