<?php

use Illuminate\Support\Facades\Event;
use Seatplus\Auth\Models\User;
use Seatplus\Auth\Services\SsoScopes\BuildScopesArrayService;
use Seatplus\Eveapi\Models\Character\CharacterInfo;

it('returns an empty array when no user owns the character', function () {
    $character = CharacterInfo::factory()->create();

    $scopes = (new BuildScopesArrayService)->get($character);

    expect($scopes)->toBe([]);
});

it('evaluates the user it was given', function () {
    createRefreshTokenWithScopes(['publicData']);
    createCorporationSsoScope(['esi-mail.read_mail.v1']);

    // A second account, created after the test user so an unconstrained ->first() would return the
    // test user instead of this one. Nothing is required of its corporation.
    $other_user = Event::fakeFor(fn () => User::factory()->create());

    $scopes = (new BuildScopesArrayService)->get($other_user);

    expect($scopes)->toHaveCount($other_user->characters->count())
        ->and(collect($scopes)->pluck('character.character_id')->all())
        ->toBe($other_user->characters->pluck('character_id')->all())
        ->and(collect($scopes)->pluck('missing_scopes')->flatten()->all())->toBe([]);
});

it('reports the scopes a character has not granted', function () {
    createRefreshTokenWithScopes(['publicData']);
    createCorporationSsoScope(['esi-mail.read_mail.v1']);

    $scopes = (new BuildScopesArrayService)->get(test()->test_user);

    expect(collect($scopes)->firstWhere('character.character_id', test()->test_character->character_id))
        ->missing_scopes->toBe(['esi-mail.read_mail.v1']);
});

it('returns missing scopes as a list even when only a later scope is missing', function () {
    createRefreshTokenWithScopes(['publicData', 'esi-skills.read_skills.v1']);
    createCorporationSsoScope(['esi-skills.read_skills.v1', 'esi-mail.read_mail.v1']);

    $scopes = (new BuildScopesArrayService)->get(test()->test_user);

    $missing = collect($scopes)
        ->firstWhere('character.character_id', test()->test_character->character_id)['missing_scopes'];

    // array_diff preserves keys, so this used to come back as [1 => '…'] and json_encode emitted an
    // object rather than a list.
    expect($missing)->toBe(['esi-mail.read_mail.v1'])
        ->and(array_is_list($missing))->toBeTrue();
});
