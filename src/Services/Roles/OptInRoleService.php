<?php

namespace Seatplus\Auth\Services\Roles;

use Seatplus\Auth\Enums\RoleMembershipStatus;
use Seatplus\Auth\Enums\RoleType;
use Seatplus\Auth\Models\User;
use Seatplus\Eveapi\Models\Alliance\AllianceInfo;
use Seatplus\Eveapi\Models\Corporation\CorporationInfo;

class OptInRoleService extends AbstractRoleService implements RoleServiceInterface
{
    /**
     * @throws \Throwable
     */
    public function addCriteriaForRole(array $entities): void
    {
        $this->addCriteria($entities, RoleType::OPT_IN);

        $this->syncMembers();
    }

    public function joinRole(User $user): void
    {
        $this->setRoleMembership(
            entity_id: $user->id,
            entity_type: User::class,
            status: RoleMembershipStatus::ACTIVE
        );
    }

    public function leaveRole(User $user): void
    {
        $this->removeRoleMembership($user);
    }
    public function syncMembers(): void
    {
        // remove all members that are not within the criteria
        $this->removeUnassignedMembers();

        // update the status of the members based on the user compliance
        $this->updateMemberStatusBasedOnUserCompliance();
    }
}
