<?php

namespace Seatplus\Auth\Http\Controllers\Auth;

use Illuminate\Foundation\Auth\AuthenticatesUsers;
use Inertia\Inertia;
use Seatplus\Web\Http\Controllers\Controller;

class LoginController extends Controller
{
    /*
    |--------------------------------------------------------------------------
    | Login Controller
    |--------------------------------------------------------------------------
    |
    | This controller handles authenticating users for the application and
    | redirecting them to your home screen. The controller uses a trait
    | to conveniently provide its functionality to your applications.
    |
    */

    use AuthenticatesUsers;

    /**
     * Where to redirect users after login.
     *
     * @var string
     */
    protected $redirectTo = '/home';

    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->middleware('guest')->except('logout');
    }

    /**
     * @return \Inertia\Response
     */
    public function showLoginForm()
    {

        // Warn if SSO has not been configured yet.
        if (strlen(config('web.config.EVE_CLIENT_ID')) < 5 || strlen(config('web.config.EVE_CLIENT_SECRET')) < 5) {
            session()->flash('warning', trans('web::auth.sso_config_warning'));
        }

        return Inertia::render('Auth/Login', [
            'login_welcome'  => trans('web::auth.login_welcome'),
            'evesso_img_src' => asset('img/evesso.png'),
        ]);
    }
}
