<?php

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Session;
use Seatplus\Auth\Http\Actions\LoginAssetsAction;

it('returns assets needed for the login page', function () {
    Config::set('services.eveonline.client_id', 'valid_client_id');
    Config::set('services.eveonline.client_secret', 'valid_client_secret');

    $action = new LoginAssetsAction;
    $result = $action();

    expect($result)->toBe([
        'login_welcome' => trans('auth::auth.login_welcome'),
        'evesso_img_src' => asset('img/evesso.png'),
    ]);
});

it('adds a warning if SSO is not configured', function () {
    Config::set('services.eveonline.client_id', '1234');
    Config::set('services.eveonline.client_secret', '1234');

    $action = new LoginAssetsAction;
    $action();

    expect(Session::get('warning'))->toBe(trans('auth::auth.sso_config_warning'));
});

it('does not add a warning if SSO is configured correctly', function () {
    Config::set('services.eveonline.client_id', 'valid_client_id');
    Config::set('services.eveonline.client_secret', 'valid_client_secret');

    $action = new LoginAssetsAction;
    $action();

    expect(Session::get('warning'))->toBeNull();
});
