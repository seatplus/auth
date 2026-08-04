<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use Seatplus\Auth\Enums\AffiliationType;
use Seatplus\Auth\Models\Permissions\Affiliation;
use Seatplus\Auth\Models\Permissions\Permission;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Services\Permissions\CanUserService;
use Seatplus\Auth\Services\Permissions\DTO\ValidateIdsDTO;
use Seatplus\Eveapi\Models\Corporation\CorporationInfo;

it('reflects a role affiliation change on the next check without flushing the permission cache', function () {
    Event::fake();

    $user = $this->test_user;
    $role = Role::create(['name' => 'auditor']);
    $role->givePermissionTo(Permission::create(['name' => 'view something']));
    $user->assignRole($role);

    $corp = CorporationInfo::factory()->create();
    $dto = new ValidateIdsDTO(corporation_id: $corp->corporation_id);

    // no affiliation yet → denied. This also warms the user_permissions cache (which now holds only
    // the role_ids per permission, not the affiliated-id set).
    expect((new CanUserService)->check($user, $dto, ['view something']))->toBeFalse();

    // grant scope by adding the affiliation directly — no observer, no cache flush.
    Affiliation::create([
        'role_id' => $role->id,
        'affiliatable_id' => $corp->corporation_id,
        'affiliatable_type' => CorporationInfo::class,
        'type' => AffiliationType::ALLOWED->value,
    ]);

    // check() resolves affiliation live, so the new scope is honoured on the very next call despite
    // the still-warm cache — the residual staleness the previous design had is gone.
    expect((new CanUserService)->check($user, $dto, ['view something']))->toBeTrue();
});

it('denies an id outside the role affiliations even when the permission is held', function () {
    Event::fake();

    $user = $this->test_user;
    $role = Role::create(['name' => 'auditor']);
    $role->givePermissionTo(Permission::create(['name' => 'view something']));
    $user->assignRole($role);

    $allowed = CorporationInfo::factory()->create();
    $other = CorporationInfo::factory()->create();

    Affiliation::create([
        'role_id' => $role->id,
        'affiliatable_id' => $allowed->corporation_id,
        'affiliatable_type' => CorporationInfo::class,
        'type' => AffiliationType::ALLOWED->value,
    ]);

    $service = new CanUserService;

    expect($service->check($user, new ValidateIdsDTO(corporation_id: $allowed->corporation_id), ['view something']))->toBeTrue()
        ->and($service->check($user, new ValidateIdsDTO(corporation_id: $other->corporation_id), ['view something']))->toBeFalse();
});

it('does not strip ids for a permission none of the user roles grant', function () {
    Event::fake();

    $user = $this->test_user;
    $role = Role::create(['name' => 'auditor']);
    $role->givePermissionTo(Permission::create(['name' => 'view something']));
    $user->assignRole($role);

    $corp = CorporationInfo::factory()->create();
    Affiliation::create([
        'role_id' => $role->id,
        'affiliatable_id' => $corp->corporation_id,
        'affiliatable_type' => CorporationInfo::class,
        'type' => AffiliationType::ALLOWED->value,
    ]);

    // the corp is affiliated, but only under 'view something'; checking a different permission
    // finds no granting role, so nothing is stripped and access is denied.
    expect((new CanUserService)->check($user, new ValidateIdsDTO(corporation_id: $corp->corporation_id), ['some other permission']))
        ->toBeFalse();
});
