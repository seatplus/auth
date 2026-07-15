<?php

declare(strict_types=1);

namespace Seatplus\Auth\Services\Roles;

use Illuminate\Database\Eloquent\Builder;
use Seatplus\Auth\Enums\RoleMembershipStatus;
use Seatplus\Auth\Models\User;
use Seatplus\Auth\Services\Roles\DTO\CriteriaData;

class AutomaticRoleService extends AbstractRoleService implements RoleServiceInterface
{
    /**
     * @throws \Throwable
     */
    public function automaticallyAssignRoleTo(CriteriaData ...$entities): void
    {
        $this->addCriteria(...$entities);

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
        // open-to-all roles assign every user; Doomheim yields no character ids to match on
        $users = $this->isOpenToAll()
            ? User::query()->get()
            : User::query()
                ->whereHas('characters', fn (Builder $query) => $query->whereIn('character_infos.character_id', $this->getAssignedCharacterIds()))
                ->get();

        $users->each(fn (User $user) => $this->setRoleMembership(
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
