<?php

use Illuminate\Validation\ValidationException;
use Seatplus\Auth\Enums\RoleMembershipStatus;
use Seatplus\Auth\Models\AccessControl\RoleMembership;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Models\User;
use Seatplus\Auth\Services\Roles\OnRequestRoleService;
use Seatplus\Eveapi\Models\Corporation\CorporationInfo;

beforeEach(function () {
    $this->role = Role::create(['name' => 'test']);
    $this->role = $this->role->refresh();

    $this->service = new OnRequestRoleService($this->role);
});

describe('adding criteria for role application', function () {
    it('adds criteria for role application with valid entities', function () {
        // Arrange
        $entities = [
            [test()->test_character->corporation_id, 'corporation'],
            [test()->test_character->alliance_id, 'alliance']
        ];

        // Act
        $this->service->addCriteriaForRoleApplication($entities);

        // Assert
        expect(RoleMembership::query()->count())->toBe(2);
    });

    it('throws validation exception for invalid entities', function () {
        // Arrange
        $entities = [
            [test()->test_character->corporation_id, 'corporation'],
            [test()->test_character->alliance_id, 'invalid']
        ];

        // Act
        $this->service->addCriteriaForRoleApplication($entities);
    })->expectException(ValidationException::class);

    it('resets criterias', function () {
        // Arrange

        // create random role membership that acts as criteria
        RoleMembership::query()->create([
            'role_id' => $this->role->id,
            'entity_id' => 12345,
            'entity_type' => CorporationInfo::class,
        ]);

        // create user role membership that acts as member and should not be deleted
        RoleMembership::query()->create([
            'role_id' => $this->role->id,
            'entity_id' => test()->test_user->id,
            'entity_type' => User::class,
        ]);

        $entities = [
            [test()->test_character->corporation_id, 'corporation'],
            [test()->test_character->alliance_id, 'alliance']
        ];

        // Act
        $this->service->addCriteriaForRoleApplication($entities);

        // Assert
        expect(RoleMembership::query()->count())->toBe(3)
            ->and(RoleMembership::query()->where('entity_type', User::class)->count())->toBe(1)
            ->and(RoleMembership::query()->where('entity_id', 12345)->count())->toBe(0);
    });
});


it('submits application for role', function () {
    // arrange
    $user = test()->test_user;

    // act
    $this->service->submitApplicationForRole($user);

    // assert
    expect(RoleMembership::query()->where('role_id', $this->role->id)->where('status', 'pending')->count())->toBe(1)
        ->and(RoleMembership::query()->where('role_id', $this->role->id)->where('entity_id', $user->id)->first())
        ->status->toBe(RoleMembershipStatus::PENDING->value)
        ->entity_id->toBe($user->id);
});

it('approving application for role', function () {
    // arrange
    $user = test()->test_user;

    // act
    $this->service->approveApplicationForRole($user);

    // assert
    expect(RoleMembership::query()->where('role_id', $this->role->id)->count())->toBe(1)
        ->and(RoleMembership::query()->where('role_id', $this->role->id)->where('entity_id', $user->id)->first())
        ->status->toBe(RoleMembershipStatus::ACTIVE->value)
        ->entity_id->toBe($user->id);
});



it('denies application for role', function () {
    // arrange
    $user = test()->test_user;
    RoleMembership::query()->create([
        'role_id' => $this->role->id,
        'entity_id' => $user->id,
        'entity_type' => User::class,
        'status' => RoleMembershipStatus::PENDING->value
    ]);

    // act
    $this->service->denyApplication($user);

    // assert
    expect(RoleMembership::query()->where('role_id', $this->role->id)->where('entity_id', $user->id)->count())->toBe(0);
});

it('removes application for role', function () {
    // arrange
    $user = test()->test_user;
    RoleMembership::query()->create([
        'role_id' => $this->role->id,
        'entity_id' => $user->id,
        'entity_type' => User::class,
        'status' => RoleMembershipStatus::PENDING->value
    ]);

    // act
    $this->service->removeApplication($user);

    // assert
    expect(RoleMembership::query()->where('role_id', $this->role->id)->where('entity_id', $user->id)->count())->toBe(0);
});

it('sets moderator status for user', function (bool $can_moderate) {
    // arrange
    $user = test()->test_user;

    // act
    $this->service->setModerator($user, $can_moderate);

    // assert
    expect(RoleMembership::query()->where('role_id', $this->role->id)->where('entity_id', $user->id)->first())
        ->can_moderate->toBe($can_moderate);
})->with([
    true,
    false
]);



describe('sync', function () {
    it('removes members outside criteria', function () {
        // Arrange
        $user = test()->test_user;

        RoleMembership::query()->create([
            'role_id' => $this->role->id,
            'entity_id' => $user->id,
            'entity_type' => User::class,
            'status' => RoleMembershipStatus::ACTIVE->value
        ]);

        // Act
        $this->service->syncMembers();

        // Assert
        expect(RoleMembership::count())->toBe(0);

    });

    it('does not removes members within criteria', function () {
        // Arrange

        // set criteria
        $test_character = test()->test_character;$test_character = test()->test_character;
        RoleMembership::query()->create([
            'role_id' => $this->role->id,
            'entity_id' => $test_character->corporation_id,
            'entity_type' => CorporationInfo::class,
        ]);

        // Add Member
        $user = test()->test_user;
        RoleMembership::query()->create([
            'role_id' => $this->role->id,
            'entity_id' => $user->id,
            'entity_type' => User::class,
            'status' => RoleMembershipStatus::ACTIVE->value
        ]);

        expect(RoleMembership::count())->toBe(2);

        //
        // Act
        $this->service->syncMembers();

        // Assert
        expect(RoleMembership::count())->toBe(2);
    });
});


