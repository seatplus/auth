<?php

use Illuminate\Support\Facades\Cache;
use Seatplus\Auth\Models\User;
use Seatplus\Eveapi\Models\Character\CharacterInfo;

it('flushes cache after creation', function (User|CharacterInfo $entity) {
    $user_id = test()->test_user->id;

    Cache::spy();

    $entity->application()->create([
        'corporation_id' => test()->test_character->corporation->corporation_id,
    ]);

    Cache::shouldHaveReceived('forget')
        ->once()
        ->with("user_permissions_{$user_id}");
})->with(function () {
    yield fn () => test()->test_user;
    yield fn () => test()->test_character;
});
