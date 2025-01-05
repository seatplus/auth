<?php

it('creates global sso scopes with provided scopes', function () {
    $scopes = ['scope1', 'scope2'];

    $service = new \Seatplus\Auth\Services\SsoScopes\GlobalSsoScopesService;
    $service->set($scopes);

    $sso_scopes = \Seatplus\Eveapi\Models\SsoScopes::query()
        ->where('type', 'global')
        ->first();


    expect($sso_scopes->selected_scopes)->toBe($scopes);
});
