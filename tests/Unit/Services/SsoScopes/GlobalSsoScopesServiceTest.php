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
