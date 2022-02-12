<?php

use Illuminate\Support\Facades\Cache;
use Seatplus\Auth\Models\User;
use Seatplus\Eveapi\Models\Character\CharacterInfo;

it('flushes cache after creation', function (User | CharacterInfo $entity) {
    Cache::shouldReceive('tags')->with(['characters_with_missing_scopes', test()->test_user->id])->andReturnSelf();
    Cache::shouldReceive('flush')->once();

    $entity->application()->create([
        'corporation_id' => test()->test_character->corporation->corporation_id,
    ]);
})->with(function () {
    yield fn () => test()->test_user;
    yield fn () => test()->test_character;
});
