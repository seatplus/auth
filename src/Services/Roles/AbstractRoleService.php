<?php

declare(strict_types=1);

namespace Seatplus\Auth\Services\Roles;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Seatplus\Auth\Enums\AffiliationType;
use Seatplus\Auth\Enums\RoleMembershipStatus;
use Seatplus\Auth\Enums\RoleType;
use Seatplus\Auth\Models\AccessControl\RoleMembership;
use Seatplus\Auth\Models\Permissions\Affiliation;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Models\User;
use Seatplus\Auth\Services\Roles\DTO\AffiliationData;
use Seatplus\Auth\Services\Roles\DTO\CriteriaData;
use Seatplus\Auth\Services\SsoScopes\IsUserCompliantService;
use Seatplus\Eveapi\Models\Alliance\AllianceInfo;
use Seatplus\Eveapi\Models\Corporation\CorporationInfo;

abstract class AbstractRoleService implements RoleServiceInterface
{
    /**
     * Doomheim (1000001) — EVE's graveyard corporation that no live character belongs to.
     * Used as a sentinel criterion meaning "everyone is eligible" (open to all): membership
     * criteria containing this corporation match every user regardless of their affiliation.
     */
    public const int EVERYONE_CORPORATION_ID = 1_000_001;

    public function __construct(
        protected Role $role,
        private readonly IsUserCompliantService $isUserCompliantService = new IsUserCompliantService(false),
    ) {}

    private function affiliateEntity(int|string $entity_id, string $entity_type, AffiliationType $affiliationType): void
    {
        Affiliation::query()->create([
            'role_id' => $this->role->id,
            'affiliatable_id' => $entity_id,
            'affiliatable_type' => $entity_type,
            'type' => $affiliationType->value,
        ]);
    }

    private function resetAffiliation(): void
    {
        Affiliation::query()
            ->where('role_id', $this->role->id)
            ->delete();
    }

    /**
     * @throws \Throwable
     */
    protected function addCriteria(CriteriaData ...$entities): void
    {
        $this->resetCriteria();

        foreach ($entities as $entity) {
            $this->setRoleMembership(
                entity_id: $entity->entity_id,
                entity_type: $entity->entityClass()
            );
        }
    }

    private function resetCriteria(): void
    {
        RoleMembership::query()
            ->where('role_id', $this->role->id)
            ->whereIn('entity_type', [CorporationInfo::class, AllianceInfo::class])
            ->delete();
    }

    private function revokeTheRolesFromUsersThatAreNotInMembers(\Illuminate\Support\Collection $member_ids): void
    {
        User::query()
            ->with('roles')
            ->whereHas('roles', fn (Builder $query) => $query->where('id', $this->role->id))
            ->whereNotIn('id', $member_ids)
            ->each(fn (User $user) => $user->removeRole($this->role));
    }

    private function getActiveMembers(): \Illuminate\Support\Collection
    {
        return $this->role->roleMemberships()
            ->where('entity_type', User::class)
            ->where('status', RoleMembershipStatus::ACTIVE)
            ->pluck('entity_id');
    }

    private function assignTheRolesToUsersThatAreInMembers(\Illuminate\Support\Collection $member_ids): void
    {
        User::query()
            ->with('roles')
            ->whereDoesntHave('roles', fn (Builder $query) => $query->where('id', $this->role->id))
            ->whereIn('id', $member_ids)
            ->each(fn (User $user) => $user->assignRole($this->role));
    }

    protected function removeRoleMembership(User $user): void
    {
        $query = RoleMembership::query()
            ->where('role_id', $this->role->id)
            ->where('entity_id', $user->id)
            ->where('entity_type', User::class);

        // Removing someone's membership (member kick / leave / denied application) must not strip
        // their moderator role: a moderator who is also a member stays a moderator. Clear only the
        // membership status in that case; otherwise remove the row entirely.
        if ((clone $query)->where('can_moderate', true)->exists()) {
            $query->update(['status' => null]);

            return;
        }

        $query->delete();
    }

    public function setRoleType(RoleType $roleType): void
    {
        $originalRoleType = $this->role->type;

        // if the role type has not changed, we return early
        if ($originalRoleType === $roleType) {
            return;
        }

        $this->role->update([
            'type' => $roleType,
        ]);

        $this->resetRoleMemberships();
    }

    protected function setRoleMembership(
        int|string $entity_id,
        string $entity_type,
        ?bool $can_moderate = null,
        ?RoleMembershipStatus $status = null
    ): void {

        $values_to_update = [];

        // Only write the columns the caller actually provided, so a status-only call (e.g.
        // addMember/approve/join) does not reset can_moderate — otherwise adding an existing
        // moderator as a member would silently strip their moderator flag. On a fresh row the
        // DB defaults apply (can_moderate = false, status = null).
        if ($can_moderate !== null) {
            $values_to_update['can_moderate'] = $can_moderate;
        }

        if ($status) {
            $values_to_update['status'] = $status->value;
        }

        RoleMembership::query()->updateOrInsert([
            'role_id' => $this->role->id,
            'entity_id' => $entity_id,
            'entity_type' => $entity_type,
        ], $values_to_update);
    }

    protected function getAssignedCharacterIds(): array
    {
        $role = $this->role->refresh()->loadMissing(['roleMemberships.entity' => function (MorphTo $morph_to) {
            $morph_to->morphWith([CorporationInfo::class => 'characters', AllianceInfo::class => 'characters']);
        }]);

        return $role
            ->roleMemberships
            ->filter(fn (RoleMembership $role_membership) => $role_membership->entity_type === CorporationInfo::class || $role_membership->entity_type === AllianceInfo::class)
            ->pluck('entity.characters')
            ->flatten()
            ->pluck('character_id')
            ->toArray();
    }

    protected function getUnassignedMembers(): Collection
    {

        $members = RoleMembership::query()
            ->where('role_id', $this->role->id)
            ->where('entity_type', User::class);

        // open-to-all roles never remove anyone based on criteria
        if ($this->isOpenToAll()) {
            return new Collection;
        }

        $character_ids = $this->getAssignedCharacterIds();

        if (! array_filter($character_ids)) {
            return $members->get();
        }

        return $members->whereDoesntHaveMorph(
            'entity',
            [User::class],
            fn (Builder $query) => $query
                ->whereHas('characters', fn (Builder $query) => $query
                    ->whereIn('character_infos.character_id', $character_ids)
                )
        )
            ->get();
    }

    protected function removeUnassignedMembers(): void
    {
        $unassigned_members = $this->getUnassignedMembers();
        $unassigned_members->each(fn (RoleMembership $role_membership) => $role_membership->delete());
    }

    public function handleMembers(): void
    {
        // sync the members, so that only the members that are compliant are considered as active
        $this->syncMembers();

        // get the active members
        $member_ids = $this->getActiveMembers();

        // revoke the roles from users that are not in members
        $this->revokeTheRolesFromUsersThatAreNotInMembers($member_ids);

        // assign the roles to users that are in members
        $this->assignTheRolesToUsersThatAreInMembers($member_ids);
    }

    protected function isUserCompliant(User $user): bool
    {
        return $this->isUserCompliantService->check($user);
    }

    /**
     * @throws \Throwable
     */
    public function syncAffiliateManyEntities(AffiliationData ...$entity_sets): void
    {
        $this->resetAffiliation();

        foreach ($entity_sets as $entity_set) {
            $this->affiliateEntity($entity_set->entity_id, $entity_set->entityClass(), $entity_set->affiliation_type);
        }
    }

    public function updateMemberStatusBasedOnUserCompliance(): void
    {
        RoleMembership::query()
            ->where('role_id', $this->role->id)
            ->where('entity_type', User::class)
            ->whereIn('status', [RoleMembershipStatus::ACTIVE->value, RoleMembershipStatus::INACTIVE->value])
            ->with('entity')
            ->get()
            ->each(fn (RoleMembership $role_membership) => $role_membership->updateOrFail([
                'status' => $this->isUserCompliant($role_membership->entity) ? RoleMembershipStatus::ACTIVE : RoleMembershipStatus::INACTIVE,
            ]));
    }

    abstract public function syncMembers(): void;

    protected function resetRoleMemberships(): void
    {
        RoleMembership::query()
            ->where('role_id', $this->role->id)
            ->delete();
    }

    protected function isModerator(User $user): bool
    {
        return RoleMembership::query()
            ->where('role_id', $this->role->id)
            ->where('entity_id', $user->id)
            ->where('entity_type', User::class)
            ->where('can_moderate', true)
            ->exists();
    }

    /**
     * Whether the role is open to all — a criterion for the Doomheim sentinel corporation
     * ({@see self::EVERYONE_CORPORATION_ID}) marks every user as eligible.
     */
    protected function isOpenToAll(): bool
    {
        return $this->role->refresh()->loadMissing('roleMemberships')
            ->roleMemberships
            ->contains(fn (RoleMembership $role_membership) => $role_membership->entity_type === CorporationInfo::class
                && (int) $role_membership->entity_id === self::EVERYONE_CORPORATION_ID);
    }

    protected function meetsCriteria(User $user): bool
    {

        // open-to-all roles consider every user eligible
        if ($this->isOpenToAll()) {
            return true;
        }

        $assigned_character_ids = $this->getAssignedCharacterIds();

        // return early if no character is assigned
        if (empty($assigned_character_ids)) {
            return false;
        }

        return User::query()
            ->where('id', $user->id)
            ->whereHas('characters', fn (Builder $query) => $query->whereIn('character_infos.character_id', $assigned_character_ids))
            ->exists();
    }

    public function updateRoleName(string $name): void
    {
        $this->role->update([
            'name' => $name,
        ]);
    }
}
