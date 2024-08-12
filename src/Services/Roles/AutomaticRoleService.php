<?php

namespace Seatplus\Auth\Services\Roles;

use Seatplus\Auth\Enums\RoleMembershipStatus;
use Seatplus\Auth\Enums\RoleType;
use Seatplus\Auth\Models\User;

class AutomaticRoleService extends AbstractRoleService implements RoleServiceInterface
{
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

    public function canJoin(User $user): bool
    {
        return false;
    }

    public function canModerate(User $user): bool
    {
        return false;
    }

    public function canView(User $user): bool
    {
        return $this->meetsCriteria($user);
    }
}
