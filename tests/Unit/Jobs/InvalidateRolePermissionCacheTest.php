<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Cache;
use Seatplus\Auth\Jobs\InvalidateRolePermissionCache;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Models\User;
use Seatplus\Auth\Services\Permissions\CanUserService;

it('forgets the cached permission object of every user holding the role', function () {
    $role = Role::create(['name' => 'test'])->refresh();

    $holder = test()->test_user;
    $holder->assignRole($role);

    // a user without the role must be left untouched
    User::factory()->create();

    Cache::spy();

    (new InvalidateRolePermissionCache($role->id))->handle();

    Cache::shouldHaveReceived('forget')
        ->once()
        ->with(CanUserService::userPermissionCacheKey($holder->id));
});

it('is unique per role so a burst of edits coalesces', function () {
    expect((new InvalidateRolePermissionCache(42))->uniqueId())->toBe('42');
});

it('tags the job with its role id for horizon', function () {
    expect((new InvalidateRolePermissionCache(7))->tags())
        ->toBe(['Invalidate Role Permission Cache', 'role_id:7']);
});
