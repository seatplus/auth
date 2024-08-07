<?php

namespace Seatplus\Auth\Services\Roles;

use Seatplus\Auth\Enums\RoleMembershipStatus;
use Seatplus\Auth\Enums\RoleType;
use Seatplus\Auth\Models\Permissions\Role;
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

    public function automaticallyAssignRoleTo(?array $corporation_ids = [], ?array $alliance_ids = []): void
    {
        $this->setRoleType(RoleType::AUTOMATIC);

        // reset all role memberships
        $this->resetRoleMembership();

        // for each corporation_id, we assign the role to the corporation
        foreach ($corporation_ids as $corporation_id) {
            $this->automaticallyAssignRoleToCorporation($corporation_id);
        }

        foreach ($alliance_ids as $alliance_id) {
            $this->automaticallyAssignRoleToAlliance($alliance_id);
        }

        $this->handleMembers();
    }

    public function syncMembers(): void
    {
        $character_ids = $this->getAssignedCharacterIds();

        // since this is an automatic role, we directly want to assign users that have a character with the required corporation_id or alliance_id
        $users = $this->getUsersFromCharacterIds($character_ids);

        // remove members that are not within users
        $this->removeIneligibleMembers($users->pluck('id')->all());

        // add members that are not in role membership
        $users->each(fn ($user) => $this->setRoleMembership(
            entity_id: $user->id,
            entity_type: User::class,
            status: $this->isUserCompliant($user) ? RoleMembershipStatus::ACTIVE : RoleMembershipStatus::INACTIVE
        ));
    }
}
