<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Seatplus\Eveapi\Models\SsoScopes;

it('flushes cache after creation', function () {

    $user_id = test()->test_user->id;

    Cache::shouldReceive('forget')
        ->once()
        ->with("user_permissions_{$user_id}");

    SsoScopes::factory()->create();
});

it('flushes cache after updated', function () {
    Event::fakeFor(fn () => SsoScopes::factory()->create());

    $user_id = test()->test_user->id;

    Cache::shouldReceive('forget')
        ->once()
        ->with("user_permissions_{$user_id}");

    $ssoScopes = SsoScopes::first();
    $ssoScopes->morphable_id = faker()->randomNumber();
    $ssoScopes->save();
});

it('flushes cache after deleted', function () {
    Event::fakeFor(fn () => SsoScopes::factory()->create());

    $user_id = test()->test_user->id;

    Cache::shouldReceive('forget')
        ->once()
        ->with("user_permissions_{$user_id}");

    $ssoScopes = SsoScopes::first();
    $ssoScopes->delete();
});
