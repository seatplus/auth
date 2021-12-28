<?php

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Seatplus\Eveapi\Models\SsoScopes;

it('flushes cache after creation', function () {
    Cache::shouldReceive('tags')->with(['characters_with_missing_scopes'])->andReturnSelf();
    Cache::shouldReceive('flush')->once();

    SsoScopes::factory()->create();
});

it('flushes cache after updated', function () {

    Event::fakeFor(fn() => SsoScopes::factory()->create());

    Cache::shouldReceive('tags')->with(['characters_with_missing_scopes'])->andReturnSelf();
    Cache::shouldReceive('flush')->once();

    $ssoScopes = SsoScopes::first();
    $ssoScopes->morphable_id = faker()->randomNumber();
    $ssoScopes->save();

});

it('flushes cache after deleted', function () {

    Event::fakeFor(fn() => SsoScopes::factory()->create());

    Cache::shouldReceive('tags')->with(['characters_with_missing_scopes'])->andReturnSelf();
    Cache::shouldReceive('flush')->once();

    $ssoScopes = SsoScopes::first();
    $ssoScopes->delete();

});