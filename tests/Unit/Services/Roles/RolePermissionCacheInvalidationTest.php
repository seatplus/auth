<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Queue;
use Seatplus\Auth\Enums\AffiliationType;
use Seatplus\Auth\Jobs\InvalidateRolePermissionCache;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Models\User;
use Seatplus\Auth\Services\Permissions\CanUserService;
use Seatplus\Auth\Services\Roles\AbstractRoleService;
use Seatplus\Auth\Services\Roles\AutomaticRoleService;
use Seatplus\Auth\Services\Roles\DTO\AffiliationData;

it('dispatches cache invalidation when a role\'s affiliations change', function () {
    $role = Role::create(['name' => 'test'])->refresh();

    (new AutomaticRoleService($role))->syncAffiliateManyEntities(
        new AffiliationData($this->test_character->corporation_id, 'corporation', AffiliationType::ALLOWED),
    );

    Queue::assertPushed(
        InvalidateRolePermissionCache::class,
        fn (InvalidateRolePermissionCache $job) => $job->roleId === $role->id,
    );
});

it('forgets a holder\'s cached permissions when their role is revoked', function () {
    $role = Role::create(['name' => 'test'])->refresh();

    $user = $this->test_user;
    $user->assignRole($role);

    $service = new class($role) extends AbstractRoleService
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

    Cache::spy();

    // no active members remain, so handleMembers() revokes the role from every current holder
    $service->handleMembers();

    expect($user->refresh()->hasRole($role->name))->toBeFalse();

    Cache::shouldHaveReceived('forget')
        ->with(CanUserService::userPermissionCacheKey($user->id));
});
