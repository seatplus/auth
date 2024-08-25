<?php

use Seatplus\Auth\Enums\RoleType;
use Seatplus\Auth\Models\Permissions\Affiliation;
use Seatplus\Auth\Models\Permissions\Role;

beforeEach(function () {
    $this->role = Role::create(['name' => 'test']);
    $this->role = $this->role->refresh();
    $this->service = new class($this->role) extends \Seatplus\Auth\Services\Roles\AbstractRoleService
    {
        public function syncMembers(): void {}

        public function canView(\Seatplus\Auth\Models\User $user): bool
        {
            return false;
        }

        public function canJoin(\Seatplus\Auth\Models\User $user): bool
        {
            return false;
        }

        public function canModerate(\Seatplus\Auth\Models\User $user): bool
        {
            return false;
        }
    };
});

it('affiliates role to corporation and getting role on test user', function () {

    // Arrange
    $test_character = test()->test_character;
    $corporation_id = $test_character->corporation_id;
    $alliance_id = $test_character->alliance_id;

    expect(Affiliation::count())->toEqual(0);

    // act
    $this->service->syncAffiliateManyEntities([
        [$corporation_id, 'corporation', \Seatplus\Auth\Enums\AffiliationType::ALLOWED->value],
        [$test_character->character_id, 'character', \Seatplus\Auth\Enums\AffiliationType::ALLOWED->value],
        [$alliance_id, 'alliance', \Seatplus\Auth\Enums\AffiliationType::ALLOWED->value],
    ]);

    // Test
    expect(Affiliation::count())->toEqual(3)
        ->and(Affiliation::first()->affiliatable_id)->toEqual($corporation_id)
        ->and(Affiliation::first()->affiliatable_type)->toEqual(\Seatplus\Eveapi\Models\Corporation\CorporationInfo::class)
        ->and(Affiliation::first()->type)->toEqual(\Seatplus\Auth\Enums\AffiliationType::ALLOWED->value);
});

it('returns early when setting same role type', function () {

    // Arrange
    $this->role->type = RoleType::AUTOMATIC->value;
    $this->role->save();

    // Act
    $automated_role_service = new \Seatplus\Auth\Services\Roles\AutomaticRoleService($this->role);
    $automated_role_service->automaticallyAssignRoleTo([
        [1, 'corporation'],
    ]);

    // Assert
    expect($this->role->refresh()->type)->toEqual(RoleType::AUTOMATIC->value);
});

it('sets role type to', function (RoleType $role_type) {

    // Act
    $this->service->setRoleType($role_type);

    // Assert
    expect($this->role->refresh()->type)->toEqual($role_type->value);
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
