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

        $this->role = $role instanceof Role ? $role : Role::query()->findOrFail($role);

        return $this;
    }

    public function automatic(): AutomaticRoleService
    {
        return new AutomaticRoleService($this->role);
    }



}
