<?php

use Seatplus\Auth\Enums\RoleMembershipStatus;
use Seatplus\Auth\Models\AccessControl\RoleMembership;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Models\User;
use Seatplus\Auth\Services\Roles\DTO\CriteriaData;
use Seatplus\Auth\Services\Roles\OptInRoleService;
use Seatplus\Eveapi\Models\Corporation\CorporationInfo;

beforeEach(function () {
    $this->role = Role::create(['name' => 'test']);
    $this->role = $this->role->refresh();

    $this->service = new OptInRoleService($this->role);
});

it('can add criteria', function () {

    $this->service->addCriteriaForRole(
        new CriteriaData(1, 'corporation'),
    );

    expect(RoleMembership::query()->count())->toBe(1)
        ->and(RoleMembership::first())->entity_type->toBe(CorporationInfo::class);
});

it('can join role', function () {
    $test_user = $this->test_user;

    $this->service->addCriteriaForRole(
        new CriteriaData($this->test_character->corporation_id, 'corporation'),
    );

    $this->service->joinRole($test_user);

    expect(RoleMembership::query()->count())->toBe(2);
});

it('lets a non-affiliated user join when open to all', function () {
    $other_user = User::factory()->create();

    $this->service->addCriteriaForRole(
        new CriteriaData(OptInRoleService::EVERYONE_CORPORATION_ID, 'corporation'),
    );

    expect($this->service->canJoin($other_user))->toBeTrue();

    $this->service->joinRole($other_user);

    expect(RoleMembership::query()
        ->where('role_id', $this->role->id)
        ->where('entity_id', $other_user->id)
        ->where('entity_type', User::class)
        ->exists())->toBeTrue();
});

it('can leave role', function () {
    $test_user = $this->test_user;
    $this->service->addCriteriaForRole(
        new CriteriaData($this->test_character->corporation_id, 'corporation'),
    );

    $this->service->joinRole($test_user);

    expect(RoleMembership::query()->count())->toBe(2);

    $this->service->leaveRole($test_user);

    expect(RoleMembership::query()->count())->toBe(1);
});

it('syncs members', function () {
    $test_user = $this->test_user;
    $this->service->addCriteriaForRole(
        new CriteriaData($this->test_character->corporation_id, 'corporation'),
    );

    $this->service->joinRole($test_user);

    // RoleMember
    $role_member = RoleMembership::query()->where('entity_type', User::class)->get();

    expect($role_member->count())->toBe(1)
        ->and($role_member->first())->status->toBe(RoleMembershipStatus::ACTIVE->value);

    // remove criteria makes the user not meet the criteria anymore
    $this->service->addCriteriaForRole(
        new CriteriaData(1234, 'corporation'),
    );

    $this->service->syncMembers();

    expect(RoleMembership::count())->toBe(1);
});

describe('it can', function () {
    beforeEach(function () {
        $this->service->addCriteriaForRole(
            new CriteriaData($this->test_character->corporation_id, 'corporation'),
        );
    });

    it('can view', function () {
        $test_user = $this->test_user;

        expect($this->service->canView($test_user))->toBeTrue();
    });

    it('can join', function () {
        $test_user = $this->test_user;

        expect($this->service->canJoin($test_user))->toBeTrue();
    });
});

it('cannot moderate', function () {
    $test_user = $this->test_user;

    expect($this->service->canModerate($test_user))->toBeFalse();
});

it('sets moderator status for user', function (bool $can_moderate) {
    $user = $this->test_user;

    $this->service->setModerator($user, $can_moderate);

    expect(RoleMembership::query()->where('role_id', $this->role->id)->where('entity_id', $user->id)->first())
        ->can_moderate->toBe($can_moderate);
})->with([
    true,
    false,
]);
