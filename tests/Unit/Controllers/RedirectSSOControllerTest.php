<?php

use Laravel\Socialite\Contracts\Factory as Socialite;
use Mockery\MockInterface;
use Seatplus\Auth\Http\Controllers\Auth\RedirectSSOController;
use Seatplus\Auth\Services\AuthenticationService;
use Seatplus\Auth\Services\SsoScopes\GlobalSsoScopesService;
use SocialiteProviders\Eveonline\Provider;
use Symfony\Component\HttpFoundation\RedirectResponse;

beforeEach(function () {
    $this->serviceMock = mock(GlobalSsoScopesService::class);
    $this->authenticationServiceMock = mock(AuthenticationService::class);
    $this->socialiteMock = mock(Socialite::class);
    $this->controller = new RedirectSSOController($this->serviceMock, $this->authenticationServiceMock);
});

afterEach(function () {
    Mockery::close();
});

it('redirects to Eve Online authentication page when user is not authenticated', function () {
    $this->authenticationServiceMock->shouldReceive('isUserAuthenticated')->andReturn(false);
    $this->authenticationServiceMock->shouldReceive('getPreviousUrl')->andReturn('http://example.com/previous');
    $this->serviceMock->shouldReceive('get')->andReturn(['scope1', 'scope2']);
    $this->socialiteMock->shouldReceive('driver')
        ->with('eveonline')
        ->andReturn(mock(Provider::class, function (MockInterface $mock) {
            $mock->shouldReceive('scopes')->andReturnSelf();
            $mock->shouldReceive('redirect')->andReturn(new RedirectResponse('http://example.com/redirect'));
        }));

    $response = $this->controller->__invoke($this->socialiteMock);

    expect($response->getTargetUrl())->toBe('http://example.com/redirect');
});

it('redirects to Eve Online authentication page even when already authenticated (add character)', function () {
    // An authenticated user hitting this route is adding another character; they must still be sent
    // to EVE SSO (CallbackController links the new character), not bounced home.
    $this->authenticationServiceMock->shouldReceive('getPreviousUrl')->andReturn('http://example.com/previous');
    $this->serviceMock->shouldReceive('get')->andReturn(['scope1', 'scope2']);
    $this->socialiteMock->shouldReceive('driver')
        ->with('eveonline')
        ->andReturn(mock(Provider::class, function (MockInterface $mock) {
            $mock->shouldReceive('scopes')->andReturnSelf();
            $mock->shouldReceive('redirect')->andReturn(new RedirectResponse('http://example.com/redirect'));
        }));

    $response = $this->controller->__invoke($this->socialiteMock);

    expect($response->getTargetUrl())->toBe('http://example.com/redirect');
});
