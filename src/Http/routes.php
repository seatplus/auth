<?php

use Illuminate\Support\Facades\Route;

Route::namespace('Seatplus\Auth\Http\Controllers\Auth')
    ->prefix('auth')
    ->group(function () {

        // Authc
        Route::get('login', [
            'as'   => 'auth.login',
            'uses' => 'LoginController@showLoginForm',
        ]);

        Route::get('logout', [
            'as'   => 'auth.logout',
            'uses' => 'LoginController@logout',
        ]);

        // SSO
        Route::get('/eve', [
            'as'   => 'auth.eve',
            'uses' => 'SsoController@redirectToProvider',
        ]);

        Route::get('/eve/callback', [
            'as'   => 'auth.eve.callback',
            'uses' => 'SsoController@handleProviderCallback',
        ]);

    });
