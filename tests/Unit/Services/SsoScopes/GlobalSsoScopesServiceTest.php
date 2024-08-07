<?php

it('creates global sso scopes with provided scopes', function () {
    $scopes = ['scope1', 'scope2'];

    $service = new \Seatplus\Auth\Services\SsoScopes\GlobalSsoScopesService();
    $service->set($scopes);

    $this->assertDatabaseHas('sso_scopes', [
        'selected_scopes' => json_encode($scopes),
        'type' => 'global',
    ]);
});
