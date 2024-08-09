<?php

namespace Seatplus\Auth\Services\Roles;

use Seatplus\Auth\Enums\RoleMembershipStatus;
use Seatplus\Auth\Enums\RoleType;
use Seatplus\Auth\Models\User;
use Seatplus\Eveapi\Models\Alliance\AllianceInfo;
use Seatplus\Eveapi\Models\Corporation\CorporationInfo;

class AutomaticRoleService extends AbstractRoleService implements RoleServiceInterface
{
    private function automaticallyAssignRoleToCorporation(int|string $corporation_id): void
    {
        $this->setRoleMembership($corporation_id, CorporationInfo::class);
    }

    private function automaticallyAssignRoleToAlliance(int|string $alliance_id): void
    {
        $this->setRoleMembership($alliance_id, AllianceInfo::class);
    }

    /**
     * @throws \Throwable
     */
    public function automaticallyAssignRoleTo(array $entities): void
    {
        $this->addCriteria($entities, RoleType::AUTOMATIC);

        $this->handleMembers();
    }

    public function syncMembers(): void
    {
        // remove members that are not within users
        $this->removeUnassignedMembers();

        $this->addAssignedMembers();

        $this->updateMemberStatusBasedOnUserCompliance();
    }

    private function addAssignedMembers(): void
    {
        $assigned_character_ids = $this->getAssignedCharacterIds();
        $users = User::query()
            ->whereHas('characters', fn ($query) => $query->whereIn('character_infos.character_id', $assigned_character_ids))
            ->get();

        $users->each(fn ($user) => $this->setRoleMembership(
            entity_id: $user->id,
            entity_type: User::class,
            status: RoleMembershipStatus::ACTIVE
        ));
    }
}
