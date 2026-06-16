<?php

use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Contracts\Factory as Socialite;
use Mockery\MockInterface;
use Seatplus\Auth\Http\Actions\Sso\FindOrCreateUserAction;
use Seatplus\Auth\Http\Actions\Sso\UpdateRefreshTokenAction;
use Seatplus\Auth\Http\Controllers\Auth\CallbackController;
use Seatplus\Auth\Models\User;
use Seatplus\Auth\Services\AuthenticationService;
use SocialiteProviders\Manager\OAuth2\User as SocialiteUser;

it('redirects back with error message on login failure', function () {

    $socialite_user = mock(SocialiteUser::class, function (MockInterface $mock) {
        $mock->makePartial();
    });

    $socialite_user->attributes = (object) [
        'character_id' => '1', // EVE SSO provider returns this as a string
        'character_owner_hash' => faker()->sha256,
    ];
    $socialite_user->token = 'token';
    $socialite_user->refreshToken = 'refreshToken';
    $socialite_user->expiresIn = 12 * 60; // let's just say 12 minutes
    $socialite_user->user = [
        'scp' => ['esi-skills.read_skills.v1', 'esi-skills.read_skillqueue.v1'],
    ];

    $social = mock(Socialite::class, function (MockInterface $social) use ($socialite_user) {
        $social->shouldReceive('driver->user')->andReturn($socialite_user);
    });

    $find_or_create_user_action = mock(FindOrCreateUserAction::class, function (MockInterface $mock) {
        $mock->shouldReceive('__invoke')->andReturn(mock(User::class));
    });

    $update_refresh_token_action = mock(UpdateRefreshTokenAction::class, function (MockInterface $mock) {
        $mock->shouldReceive('__invoke')->andReturnNull();
    });

    $authenticationService = mock(AuthenticationService::class, function (MockInterface $mock) {
        $mock->shouldReceive('isUserAuthenticated')->andReturnFalse();
        $mock->shouldReceive('loginUser')->andReturnFalse();
    });

    $controller = new CallbackController($authenticationService);

    $response = $controller($social, $find_or_create_user_action, $update_refresh_token_action);

    expect($response)->toBeInstanceOf(RedirectResponse::class)
        ->and(session('error'))->toBe('Login failed. Please contact your administrator.');
});

it('redirects back if different character id is provided', function () {
    $socialite_user = mock(SocialiteUser::class, function (MockInterface $mock) {
        $mock->makePartial();
    });

    $socialite_user->attributes = (object) [
        'character_id' => '1', // EVE SSO provider returns this as a string
        'character_owner_hash' => faker()->sha256,
    ];
    $socialite_user->token = 'token';
    $socialite_user->refreshToken = 'refreshToken';
    $socialite_user->expiresIn = 12 * 60; // let's just say 12 minutes
    $socialite_user->user = [
        'scp' => ['esi-skills.read_skills.v1', 'esi-skills.read_skillqueue.v1'],
    ];

    $social = mock(Socialite::class, function (MockInterface $social) use ($socialite_user) {
        $social->shouldReceive('driver->user')->andReturn($socialite_user);
    });

    $find_or_create_user_action = mock(FindOrCreateUserAction::class, function (MockInterface $mock) {
        $mock->shouldReceive('__invoke')->andReturn(mock(User::class));
    });

    $update_refresh_token_action = mock(UpdateRefreshTokenAction::class, function (MockInterface $mock) {
        $mock->shouldReceive('__invoke')->andReturnNull();
    });

    $authenticationService = mock(AuthenticationService::class, function (MockInterface $mock) {
        $mock->shouldReceive('isUserAuthenticated')->andReturnTrue();
        $mock->shouldReceive('getSessionValue')->with('sso_scopes')->andReturn(['esi-skills.read_skills.v1', 'esi-skills.read_skillqueue.v1']);
        $mock->shouldReceive('getSessionValue')->with('step_up')->andReturn(2);
        $mock->shouldReceive('flashMessage')
            ->once()
            ->with('error', 'Please make sure to select the same character to step up on CCP as on seatplus.')
            ->andReturnNull();
    });

    $controller = new CallbackController($authenticationService);

    $response = $controller($social, $find_or_create_user_action, $update_refresh_token_action);

    expect($response)->toBeInstanceOf(RedirectResponse::class);
});
