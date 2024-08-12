<?php

namespace Seatplus\Auth\Services\Roles;

use Seatplus\Auth\Enums\RoleMembershipStatus;
use Seatplus\Auth\Models\User;

class ManualRoleService extends AbstractRoleService implements RoleServiceInterface
{
    public function setModerator(User $user, bool $can_moderate = true): void
    {
        $this->setRoleMembership(
            entity_id: $user->id,
            entity_type: User::class,
            can_moderate: $can_moderate
        );
    }

    public function addMember(User $user): void
    {
        $this->setRoleMembership(
            entity_id: $user->id,
            entity_type: User::class,
            status: RoleMembershipStatus::ACTIVE
        );
    }

    public function removeMember(User $user): void
    {
        $this->removeRoleMembership($user);
    }

    public function syncMembers(): void
    {
        // update the status of the members based on the user compliance
        $this->updateMemberStatusBasedOnUserCompliance();
    }

    public function canJoin(User $user): bool
    {
        return false;
    }

    public function canModerate(User $user): bool
    {
        return $this->isModerator($user);
    }

    public function canView(User $user): bool
    {
        return false;
    }
}
