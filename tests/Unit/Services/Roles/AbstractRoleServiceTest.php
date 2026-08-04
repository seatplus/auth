<?php

use Seatplus\Auth\Enums\AffiliationType;
use Seatplus\Auth\Enums\RoleType;
use Seatplus\Auth\Models\Permissions\Affiliation;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Models\User;
use Seatplus\Auth\Services\Roles\AbstractRoleService;
use Seatplus\Auth\Services\Roles\AutomaticRoleService;
use Seatplus\Auth\Services\Roles\DTO\AffiliationData;
use Seatplus\Auth\Services\Roles\DTO\CriteriaData;
use Seatplus\Eveapi\Models\Corporation\CorporationInfo;

beforeEach(function () {
    // Spatie annotates Role::create() as RoleContract|SpatieRole, so the subclass is lost.
    /** @var Role $role */
    $role = Role::create(['name' => 'test']);
    $this->role = $role->refresh();
    $this->service = new class($this->role) extends AbstractRoleService
    {
        public function syncMembers(): void {}

        public function canView(User $user): bool
        {
            return false;
        }

        public function canJoin(User $user): bool
        {
            return false;
        }

        public function canModerate(User $user): bool
        {
            return false;
        }
    };
});

it('affiliates role to corporation and getting role on test user', function () {

    // Arrange
    $test_character = $this->test_character;
    $corporation_id = $test_character->corporation_id;
    $alliance_id = $test_character->alliance_id;

    expect(Affiliation::count())->toEqual(0);

    // act
    $this->service->syncAffiliateManyEntities(
        new AffiliationData($corporation_id, 'corporation', AffiliationType::ALLOWED),
        new AffiliationData($test_character->character_id, 'character', AffiliationType::ALLOWED),
        new AffiliationData($alliance_id, 'alliance', AffiliationType::ALLOWED),
    );

    // Test
    expect(Affiliation::count())->toEqual(3)
        ->and(Affiliation::first()->affiliatable_id)->toEqual($corporation_id)
        ->and(Affiliation::first()->affiliatable_type)->toEqual(CorporationInfo::class)
        ->and(Affiliation::first()->type)->toEqual(AffiliationType::ALLOWED->value);
});

it('returns early when setting same role type', function () {

    // Arrange
    $this->role->type = RoleType::AUTOMATIC;
    $this->role->save();

    // Act
    $automated_role_service = new AutomaticRoleService($this->role);
    $automated_role_service->automaticallyAssignRoleTo(
        new CriteriaData(1, 'corporation'),
    );

    // Assert
    expect($this->role->refresh()->type)->toEqual(RoleType::AUTOMATIC);
});

it('sets role type to', function (RoleType $role_type) {

    // Act
    $this->service->setRoleType($role_type);

    // Assert
    expect($this->role->refresh()->type)->toEqual($role_type);
})->with([
    RoleType::AUTOMATIC,
    RoleType::ON_REQUEST,
    RoleType::OPT_IN,
    RoleType::MANUAL,
]);

it('rename role', function () {

    // Act
    $this->service->updateRoleName('new name');

    // Assert
    expect($this->role->refresh()->name)->toEqual('new name');
});
