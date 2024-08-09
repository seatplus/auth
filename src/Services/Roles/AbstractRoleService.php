<?php

namespace Seatplus\Auth\Services\Roles;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Seatplus\Auth\Enums\AffiliationType;
use Seatplus\Auth\Enums\RoleMembershipStatus;
use Seatplus\Auth\Enums\RoleType;
use Seatplus\Auth\Models\AccessControl\RoleMembership;
use Seatplus\Auth\Models\Permissions\Affiliation;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Models\User;
use Seatplus\Auth\Services\SsoScopes\IsUserCompliantService;
use Seatplus\Eveapi\Models\Alliance\AllianceInfo;
use Seatplus\Eveapi\Models\Character\CharacterInfo;
use Seatplus\Eveapi\Models\Corporation\CorporationInfo;

abstract class AbstractRoleService implements RoleServiceInterface
{
    public function __construct(
        protected Role $role
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
    private function validateAffiliationEntities(array $entity_sets): void
    {
        $validator = validator($entity_sets, [
            '*.0' => 'required|integer',
            '*.1' => ['required', 'string', Rule::in(['character', 'corporation', 'alliance'])],
            '*.2' => [
                'required',
                'string',
                Rule::in(array_map(fn (AffiliationType $affiliationType) => $affiliationType->value, AffiliationType::cases())),
            ],
        ]);

        throw_if($validator->fails(), ValidationException::withMessages($validator->errors()->toArray()));
    }

    /**
     * @throws \Throwable
     */
    private function validateCriteria(array $entities): void
    {
        $validator = validator($entities, [
            '*.0' => 'required|integer',
            '*.1' => ['required', 'string', Rule::in(['corporation', 'alliance'])],
        ]);

        throw_if($validator->fails(), ValidationException::withMessages($validator->errors()->toArray()));
    }

    private function resetCriteria(): void
    {
        RoleMembership::query()
            ->where('role_id', $this->role->id)
            ->whereIn('entity_type', [CorporationInfo::class, AllianceInfo::class])
            ->delete();
    }

    /**
     * @throws \Throwable
     */
    protected function addCriteria(array $entities, RoleType $roleType): void
    {
        $this->validateCriteria($entities);

        $this->setRoleType($roleType);

        $this->resetCriteria();

        foreach ($entities as $entity) {

            $entity_type = match ($entity[1]) {
                'corporation' => CorporationInfo::class,
                'alliance' => AllianceInfo::class,
            };

            $this->setRoleMembership(
                entity_id: $entity[0],
                entity_type: $entity_type
            );
        }
    }

    private function revokeTheRolesFromUsersThatAreNotInMembers(\Illuminate\Support\Collection $member_ids): void
    {
        User::query()
            ->whereHas('roles', fn ($query) => $query->where('id', $this->role->id))
            ->whereNotIn('id', $member_ids)
            ->each(fn ($user) => $user->removeRole($this->role));
    }

    private function getActiveMembers(): \Illuminate\Support\Collection
    {
        return $this->role->role_memberships()
            ->where('entity_type', User::class)
            ->where('status', RoleMembershipStatus::ACTIVE)
            ->pluck('entity_id');
    }

    private function assignTheRolesToUsersThatAreInMembers(\Illuminate\Support\Collection $member_ids): void
    {
        User::query()
            ->whereDoesntHave('roles', fn ($query) => $query->where('id', $this->role->id))
            ->whereIn('id', $member_ids)
            ->each(fn ($user) => $user->assignRole($this->role));
    }

    protected function removeRoleMembership(User $user): void
    {
        RoleMembership::query()
            ->where('role_id', $this->role->id)
            ->where('entity_id', $user->id)
            ->where('entity_type', User::class)
            ->delete();
    }

    protected function setRoleType(RoleType $roleType): void
    {
        $originalRoleType = $this->role->type;

        // if the role type has not changed, we return early
        if ($originalRoleType === $roleType->value) {
            return;
        }

        $this->role->update([
            'type' => $roleType->value,
        ]);

        $this->resetRoleMemberships();
    }

    protected function setRoleMembership(
        int|string $entity_id,
        string $entity_type,
        bool $can_moderate = false,
        ?RoleMembershipStatus $status = null
    ): void {

        $values_to_update = ['can_moderate' => $can_moderate];

        // if $status is set, we add it to the values to update
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
        $role = $this->role->loadMissing(['role_memberships.entity' => function (MorphTo $morph_to) {
            $morph_to->morphWith([CorporationInfo::class => 'characters', AllianceInfo::class => 'characters']);
        }]);

        return $role
            ->role_memberships
            ->filter(fn ($role_membership) => $role_membership->entity_type === CorporationInfo::class || $role_membership->entity_type === AllianceInfo::class)
            ->pluck('entity.characters')
            ->flatten()
            ->pluck('character_id')
            ->toArray();
    }

    protected function getRoleMembers(): Collection
    {

        return RoleMembership::query()
            ->where('role_id', $this->role->id)
            ->where('entity_type', User::class)
            ->whereHasMorph('entity', [User::class], fn (Builder $query) => $query
                ->whereHas('characters', function ($query) {
                    $character_ids = $this->getAssignedCharacterIds();

                    if (!empty($character_ids)) {
                        $query->whereNotIn('character_infos.character_id', $character_ids);
                    }
                })
            )
            ->get();
    }

    protected function removeUnassignedMembers(): void
    {
        $unassigned_members = $this->getRoleMembers();
        $unassigned_members->each(fn (RoleMembership $role_membership) =>$role_membership->delete());
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
        // build a service to check if user is compliant. We do not consider applications here
        $is_user_compliant_service = new IsUserCompliantService(false);

        return $is_user_compliant_service->check($user);
    }

    /**
     * @throws \Throwable
     */
    public function syncAffiliateManyEntities(array $entity_sets): void
    {
        $this->validateAffiliationEntities($entity_sets);

        $this->resetAffiliation();

        foreach ($entity_sets as $entity_set) {

            [$entity_id, $entity_type, $affiliation_type] = $entity_set;

            $entity_type = match ($entity_type) {
                'character' => CharacterInfo::class,
                'corporation' => CorporationInfo::class,
                'alliance' => AllianceInfo::class,
            };

            $this->affiliateEntity($entity_id, $entity_type, AffiliationType::from($affiliation_type));
        }
    }

    public function updateMemberStatusBasedOnUserCompliance(): void
    {
        RoleMembership::query()
            ->where('role_id', $this->role->id)
            ->where('entity_type', User::class)
            ->whereIn('status', [RoleMembershipStatus::ACTIVE->value, RoleMembershipStatus::INACTIVE->value])
            ->get()
            ->each(fn (RoleMembership $role_membership) => $role_membership->updateOrFail([
                'status' => $this->isUserCompliant($role_membership->entity) ? RoleMembershipStatus::ACTIVE : RoleMembershipStatus::INACTIVE
            ]));
    }

    abstract public function syncMembers(): void;

    protected function resetRoleMemberships(): void
    {
        RoleMembership::query()
            ->where('role_id', $this->role->id)
            ->delete();
    }
}
