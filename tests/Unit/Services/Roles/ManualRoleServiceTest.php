<?php

use Seatplus\Auth\Enums\RoleMembershipStatus;
use Seatplus\Auth\Models\AccessControl\RoleMembership;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Models\User;
use Seatplus\Auth\Services\Roles\ManualRoleService;

beforeEach(function () {
    $this->role = Role::create(['name' => 'test']);
    $this->role = $this->role->refresh();

    $this->service = new ManualRoleService($this->role);
});

it('can add a member', function () {
    $test_user = test()->test_user;

    $this->service->addMember($test_user);

    expect(RoleMembership::query()->count())->toBe(1)
        ->and(RoleMembership::first())->entity_type->toBe(User::class);
});

it('can remove a member', function () {
    $test_user = test()->test_user;

    $this->service->addMember($test_user);

    expect(RoleMembership::query()->count())->toBe(1);

    $this->service->removeMember($test_user);

    expect(RoleMembership::query()->count())->toBe(0);
});

it('can add user as moderator and does not change status', function () {
    $test_user = test()->test_user;

    $this->service->addMember($test_user);

    expect(RoleMembership::first())->status->toBe(RoleMembershipStatus::ACTIVE->value);

    $this->service->setModerator($test_user);

    expect(RoleMembership::first())->status->toBe(RoleMembershipStatus::ACTIVE->value)
        ->can_moderate->toBeTrue();

    $this->service->setModerator($test_user, false);

    expect(RoleMembership::first())->status->toBe(RoleMembershipStatus::ACTIVE->value)
        ->can_moderate->toBeFalse();
});

it('syncs members', function () {
    $test_user = test()->test_user;

    $this->service->addMember($test_user);

    expect(RoleMembership::first())->status->toBe(RoleMembershipStatus::ACTIVE->value);

    $this->service->syncMembers();

    expect(RoleMembership::first())->status->toBe(RoleMembershipStatus::ACTIVE->value);
});

it('can view', function () {
    expect($this->service->canView(test()->test_user))->toBeFalse();
});

it('can join', function () {
    expect($this->service->canJoin(test()->test_user))->toBeFalse();
});

it('can moderate', function () {

    $this->service->setModerator(test()->test_user);

    expect($this->service->canModerate(test()->test_user))->toBeTrue();
});
