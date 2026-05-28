<?php

namespace Seatplus\Auth\Services\Roles;

use Seatplus\Auth\Enums\RoleType;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Models\User;

class BaseRoleService
{
    public function __construct(
        private ?Role $role = null
    ) {}

    public static function make(Role|string|int $role): self
    {
        return (new self)->for($role);
    }

    public function for(Role|string|int $role): self
    {

        /** @var Role $resolved_role */
        $resolved_role = match (true) {
            $role instanceof Role => $role,
            is_string($role) => Role::findByName($role),
            is_int($role) => Role::findById($role),
        };

        $this->role = $resolved_role;

        return $this;
    }

    public function automatic(): AutomaticRoleService
    {
        return new AutomaticRoleService($this->role);
    }

    public function onRequest(): OnRequestRoleService
    {
        return new OnRequestRoleService($this->role);
    }

    public function manual(): ManualRoleService
    {
        return new ManualRoleService($this->role);
    }

    public function optIn(): OptInRoleService
    {
        return new OptInRoleService($this->role);
    }

    /**
     * @throws \Exception
     */
    public function getTypeService(): RoleServiceInterface
    {
        return match ($this->getType()) {
            RoleType::AUTOMATIC => $this->automatic(),
            RoleType::ON_REQUEST => $this->onRequest(),
            RoleType::MANUAL => $this->manual(),
            RoleType::OPT_IN => $this->optIn(),
        };
    }

    public function getType(): RoleType
    {
        return $this->role->type;
    }

    public function handleMembers(): void
    {
        $this->getTypeService()->handleMembers();
    }

    public function canView(User $user): bool
    {
        return $this->getTypeService()->canView($user);
    }

    public function canJoin(User $user): bool
    {
        return $this->getTypeService()->canJoin($user);
    }

    public function canModerate(User $user): bool
    {
        return $this->getTypeService()->canModerate($user);
    }
}
