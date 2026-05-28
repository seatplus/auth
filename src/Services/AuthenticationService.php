<?php

namespace Seatplus\Auth\Services;

use Illuminate\Contracts\Auth\Guard;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Facades\Redirect;
use Seatplus\Auth\Models\User;

class AuthenticationService
{
    public function __construct(protected Guard $auth, protected Session $session) {}

    /**
     * Login the user.
     *
     * This method returns a boolean as a status flag for the
     * login routine. If a false is returned, it might mean
     * that that account is not allowed to sign in.
     */
    public function loginUser(User $user): bool
    {
        try {
            $this->auth->login($user, true);
        } catch (\Exception $e) {
            report($e);

            return false;
        }

        return true;
    }

    public function setIntendedUrl(string $url): void
    {
        Redirect::setIntendedUrl($url);
    }

    public function getPreviousUrl(): string
    {
        return $this->session->previousUrl();
    }

    public function flashMessage(string $type, string $message): void
    {
        $this->session->flash($type, $message);
    }

    public function getSessionValue(string $key): mixed
    {
        return $this->session->pull($key);
    }

    public function isUserAuthenticated(): bool
    {
        return $this->auth->check();
    }
}
