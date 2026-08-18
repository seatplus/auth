<?php

use Seatplus\Auth\Services\SsoScopes\IsUserCompliantService;

it('reports a user with every required scope as compliant', function () {
    createRefreshTokenWithScopes(['publicData', 'esi-mail.read_mail.v1']);
    createCorporationSsoScope(['esi-mail.read_mail.v1']);

    $service = new IsUserCompliantService;

    expect($service->check(test()->test_user))->toBeTrue()
        ->and($service->getMissingCharacterScopes(test()->test_user))->toBe([]);
});

it('reports a user missing a required scope as not compliant', function () {
    createRefreshTokenWithScopes(['publicData']);
    createCorporationSsoScope(['esi-mail.read_mail.v1']);

    $service = new IsUserCompliantService;

    expect($service->check(test()->test_user))->toBeFalse();
});

it('names the character that is missing scopes', function () {
    createRefreshTokenWithScopes(['publicData']);
    createCorporationSsoScope(['esi-mail.read_mail.v1']);

    $missing = (new IsUserCompliantService)->getMissingCharacterScopes(test()->test_user);

    // Only non-compliant characters are returned, and each keeps the character it belongs to — which is
    // what a caller needs to tell somebody *which* character to re-authorise.
    expect($missing)->toHaveCount(1)
        ->and($missing[0]['character']->character_id)->toBe(test()->test_character->character_id)
        ->and($missing[0]['missing_scopes'])->toBe(['esi-mail.read_mail.v1'])
        ->and($missing[0]['required_scopes'])->toContain('esi-mail.read_mail.v1');
});

it('still reports bare scope lists through getMissingScopes', function () {
    createRefreshTokenWithScopes(['publicData']);
    createCorporationSsoScope(['esi-mail.read_mail.v1']);

    // Kept for callers that only want the scopes; unlike getMissingCharacterScopes() it drops the
    // character and includes an entry per character, compliant or not.
    expect((new IsUserCompliantService)->getMissingScopes(test()->test_user))
        ->toBe([['esi-mail.read_mail.v1']]);
});
