<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Seatplus\Eveapi\Models\SsoScopes;

it('flushes cache after creation', function () {

    $user_id = test()->test_user->id;

    Cache::spy();

    SsoScopes::factory()->create();

    Cache::shouldHaveReceived('forget')
        ->once()
        ->with("user_permissions_{$user_id}");
});

it('flushes cache after updated', function () {
    Event::fakeFor(fn () => SsoScopes::factory()->create());

    $user_id = test()->test_user->id;

    Cache::spy();

    $ssoScopes = SsoScopes::first();
    $ssoScopes->morphable_id = faker()->randomNumber(5);
    $ssoScopes->save();

    Cache::shouldHaveReceived('forget')
        ->once()
        ->with("user_permissions_{$user_id}");
});

it('flushes cache after deleted', function () {
    Event::fakeFor(fn () => SsoScopes::factory()->create());

    $user_id = test()->test_user->id;

    Cache::spy();

    $ssoScopes = SsoScopes::first();
    $ssoScopes->delete();

    Cache::shouldHaveReceived('forget')
        ->once()
        ->with("user_permissions_{$user_id}");
});
