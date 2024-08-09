<?php

namespace Seatplus\Auth\Services\Roles;

use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Seatplus\Auth\Enums\RoleMembershipStatus;
use Seatplus\Auth\Enums\RoleType;
use Seatplus\Auth\Models\AccessControl\RoleMembership;
use Seatplus\Auth\Models\User;
use Seatplus\Eveapi\Models\Alliance\AllianceInfo;
use Seatplus\Eveapi\Models\Corporation\CorporationInfo;

class OnRequestRoleService extends AbstractRoleService implements RoleServiceInterface
{

    /**
     * @throws \Throwable
     */
    public function addCriteriaForRoleApplication(array $entities): void
    {
        $this->validate($entities, ['corporation', 'alliance']);

        $this->setRoleType(RoleType::ON_REQUEST);

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

        $this->syncMembers();
    }

    public function submitApplicationForRole(User $user): void
    {
        $this->setRoleMembership(
            entity_id: $user->id,
            entity_type: User::class,
            status: RoleMembershipStatus::PENDING
        );
    }

    public function approveApplicationForRole(User $user): void
    {
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

    /**
     * @throws \Throwable
     */
    private function validate(array $entities, array $entity_types): void
    {
        $validator = validator($entities, [
            '*.0' => 'required|integer',
            '*.1' => ['required', 'string', Rule::in($entity_types)],
        ]);

        throw_if($validator->fails(), ValidationException::withMessages($validator->errors()->toArray()));
    }

    public function syncMembers(): void
    {
        // remove all members that are not within the criteria
        $this->removeUnassignedMembers();

        // update the status of the members based on the user compliance
        $this->updateMemberStatusBasedOnUserCompliance();
    }

    private function resetCriteria(): void
    {
        RoleMembership::query()
            ->where('role_id', $this->role->id)
            ->whereIn('entity_type', [CorporationInfo::class, AllianceInfo::class])
            ->delete();
    }
}
