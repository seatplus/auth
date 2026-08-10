<?php

use Seatplus\Auth\Services\SsoScopes\GlobalSsoScopesService;
use Seatplus\Eveapi\Models\SsoScopes;

it('creates global sso scopes with provided scopes', function () {
    $scopes = ['scope1', 'scope2'];

    $service = new GlobalSsoScopesService;
    $service->set($scopes);

    $sso_scopes = SsoScopes::query()
        ->where('type', 'global')
        ->first();

    expect($sso_scopes->selected_scopes)->toBe($scopes);
});

it('returns a flat list of scopes', function () {
    (new GlobalSsoScopesService)->set(['esi-assets.read_assets.v1', 'esi-skills.read_skills.v1']);

    $scopes = (new GlobalSsoScopesService)->get();

    // selected_scopes is itself an array, so plucking the column used to yield [[…]] — which broke
    // RedirectSSOController, where Socialite implodes the list into the scope parameter.
    expect($scopes)->toBe(['esi-assets.read_assets.v1', 'esi-skills.read_skills.v1'])
        ->and(implode(' ', $scopes))->not->toContain('Array');
});

it('merges and de-duplicates several global rows', function () {
    $service = new GlobalSsoScopesService;

    $service->set(['esi-assets.read_assets.v1', 'esi-skills.read_skills.v1']);
    $service->set(['esi-skills.read_skills.v1', 'esi-mail.read_mail.v1']);

    expect($service->get())->toBe([
        'esi-assets.read_assets.v1',
        'esi-skills.read_skills.v1',
        'esi-mail.read_mail.v1',
    ]);
});

it('returns an empty list when nothing is configured', function () {
    expect((new GlobalSsoScopesService)->get())->toBe([]);
});
