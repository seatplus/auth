<?php

use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Session\Session;
use Seatplus\Auth\Models\User;
use Seatplus\Auth\Services\AuthenticationService;

beforeEach(function () {
    $this->authMock = mock(Guard::class);
    $this->sessionMock = mock(Session::class);
    $this->authenticationService = new AuthenticationService($this->authMock, $this->sessionMock);
});

afterEach(function () {
    Mockery::close();
});

it('logs in user successfully', function () {
    $user = mock(User::class);
    $this->authMock->shouldReceive('login')->with($user, true)->andReturnNull();

    $result = $this->authenticationService->loginUser($user);

    expect($result)->toBeTrue();
});

it('fails to log in user and reports exception', function () {
    $user = mock(User::class);
    $this->authMock->shouldReceive('login')->with($user, true)->andThrow(new Exception);

    $result = $this->authenticationService->loginUser($user);

    expect($result)->toBeFalse();
});

it('sets intended URL', function () {
    $url = 'http://example.com';
    Redirect::shouldReceive('setIntendedUrl')->with($url)->once();

    $this->authenticationService->setIntendedUrl($url);
});

it('flashes a message to the session', function () {
    $type = 'error';
    $message = 'An error occurred';
    $this->sessionMock->shouldReceive('flash')->with($type, $message)->once();

    $this->authenticationService->flashMessage($type, $message);
});

it('retrieves and removes a session value', function () {
    $key = 'step_up';
    $value = 'some_value';
    $this->sessionMock->shouldReceive('pull')->with($key)->andReturn($value);

    $result = $this->authenticationService->getSessionValue($key);

    expect($result)->toBe($value);
});

it('checks if user is authenticated', function () {
    $this->authMock->shouldReceive('check')->andReturn(true);

    $result = $this->authenticationService->isUserAuthenticated();

    expect($result)->toBeTrue();
});

it('retrieves the previous URL from the session', function () {
    $previousUrl = 'http://example.com/previous';
    $this->sessionMock->shouldReceive('previousUrl')->andReturn($previousUrl);

    $result = $this->authenticationService->getPreviousUrl();

    expect($result)->toBe($previousUrl);
});
