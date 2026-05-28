<?php

declare(strict_types=1);

namespace Seatplus\Auth\Http\Actions;

class LoginAssetsAction
{
    /**
     * Return the assets needed for the login page
     * adds a warning if the SSO is not configured yet
     *
     * @return array
     */
    public function __invoke()
    {
        $config = config('services.eveonline');
        $client_id = $config['client_id'];
        $client_secret = $config['client_secret'];

        // check if the client_id and client_secret are set and longer than 5 characters
        if (strlen((string) $client_id) < 5 || strlen((string) $client_secret) < 5) {
            // Warn if SSO has not been configured yet.
            session()->flash('warning', trans('auth::auth.sso_config_warning'));
        }

        return [
            'login_welcome' => trans('auth::auth.login_welcome'),
            'evesso_img_src' => asset('img/evesso.png'),
        ];
    }
}
