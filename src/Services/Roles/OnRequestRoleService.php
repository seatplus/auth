<?php

namespace Seatplus\Auth\Services\Roles;

use Seatplus\Auth\Enums\RoleMembershipStatus;
use Seatplus\Auth\Enums\RoleType;
use Seatplus\Auth\Models\User;

class OnRequestRoleService extends AbstractRoleService implements RoleServiceInterface
{
    /**
     * @throws \Throwable
     */
    public function addCriteriaForRoleApplication(array $entities): void
    {
        $this->addCriteria($entities, RoleType::ON_REQUEST);

        $this->syncMembers();
    }

    public function submitApplicationForRole(User $user): void
    {

        $meets_criteria = $this->meetsCriteria($user);

        if (! $meets_criteria) {
            throw new \Exception('User does not meet criteria to join role');
        }

        $this->setRoleMembership(
            entity_id: $user->id,
            entity_type: User::class,
            status: RoleMembershipStatus::PENDING
        );
    }

    /**
     * @throws \Exception
     */
    public function approveApplicationForRole(User $user): void
    {

        $meets_criteria = $this->meetsCriteria($user);

        if (! $meets_criteria) {
            $this->removeRoleMembership($user);
            throw new \Exception('User does not meet criteria to join role');
        }

        $this->setRoleMembership(
            entity_id: $user->id,
            entity_type: User::class,
            status: RoleMembershipStatus::ACTIVE
        );
    }

    public function denyApplication(User $user): void
    {
        $this->removeRoleMembership($user);
    }

    public function removeApplication(User $user): void
    {
        $this->removeRoleMembership($user);
    }

    public function setModerator(User $user, bool $can_moderate = true): void
    {
        $this->setRoleMembership(
            entity_id: $user->id,
            entity_type: User::class,
            can_moderate: $can_moderate
        );
    }

    public function syncMembers(): void
    {
        // remove all members that are not within the criteria
        $this->removeUnassignedMembers();

        // update the status of the members based on the user compliance
        $this->updateMemberStatusBasedOnUserCompliance();
    }

    public function canView(User $user): bool
    {
        return $this->meetsCriteria($user);
    }

    public function canJoin(User $user): bool
    {
        return $this->meetsCriteria($user);
    }

    public function canModerate(User $user): bool
    {
        return $this->isModerator($user);
    }
}
