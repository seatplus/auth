<?php

use Seatplus\Auth\Services\SsoScopes\BuildScopesArrayService;
use Seatplus\Eveapi\Models\Character\CharacterInfo;

it('returns an empty array when no user owns the character', function () {
    $character = CharacterInfo::factory()->create();

    $scopes = (new BuildScopesArrayService)->get($character);

    expect($scopes)->toBe([]);
});
