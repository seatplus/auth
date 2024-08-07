<?php

namespace Seatplus\Auth\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Contracts\Factory as Socialite;
use Seatplus\Auth\Containers\EveUser;
use Seatplus\Auth\Http\Actions\Sso\FindOrCreateUserAction;
use Seatplus\Auth\Http\Actions\Sso\UpdateRefreshTokenAction;
use Seatplus\Auth\Jobs\UserRolesSync;
use Seatplus\Auth\Models\User;
use SocialiteProviders\Manager\OAuth2\User as SocialiteUser;

class CallbackController
{
    private bool $should_redirect = false;

    public function __invoke(
        Socialite $social,
        FindOrCreateUserAction $find_or_create_user_action,
        UpdateRefreshTokenAction $update_refresh_token_action
    ): RedirectResponse {

        /* @var SocialiteUser $socialite_user */
        $socialite_user = $social->driver('eveonline')->user();
        $return_url = session()->pull('rurl');

        $eve_data = new EveUser(
            character_id: data_get($socialite_user, 'attributes.character_id'),
            character_owner_hash: data_get($socialite_user, 'attributes.character_owner_hash'),
            token: data_get($socialite_user, 'token'),
            refreshToken: data_get($socialite_user, 'refreshToken'),
            expiresIn: data_get($socialite_user, 'expiresIn'),
            user: data_get($socialite_user, 'user'),
        );

        // if return url was set, set the intended URL
        if ($return_url) {
            redirect()->setIntendedUrl($return_url);
        }

        // check if the requested scopes matches the provided scopes
        if (auth()->user()) {
            $this->checkForInvalidProviderCallback($eve_data);
            $this->checkIfDifferentCharacterIdHasBeenProvided($eve_data);

            if ($this->should_redirect) {
                return redirect()->intended();
            }
        }

        // Get or create the User bound to this login.
        $user = $find_or_create_user_action($eve_data);

        /*
         * Update the refresh token for this character.
         */
        $update_refresh_token_action($eve_data);

        if (! $this->loginUser($user)) {
            return redirect()->route('auth.login')
                ->with('error', 'Login failed. Please contact your administrator.');
        }

        session()->flash('success', 'Character added/updated successfully');

        UserRolesSync::dispatch($user)->onQueue('high');

        return redirect()->intended();
    }

    /**
     * Login the user.
     *
     * This method returns a boolean as a status flag for the
     * login routine. If a false is returned, it might mean
     * that that account is not allowed to sign in.
     */
    private function loginUser(User $user): bool
    {
        // Login and "remember" the given user...
        try {
            Auth::login($user, true);
        } catch (\Exception $e) {
            report($e);

            return false;
        }

        return true;
    }

    private function checkForInvalidProviderCallback(EveUser $user): void
    {
        $missing_scopes = array_diff(session()->pull('sso_scopes'), $user->getScopes());

        if (empty($missing_scopes)) {
            return;
        }

        session()->flash('error', 'Something might have gone wrong. You might have changed the requested scopes on esi, please refer from doing so.');
        $this->should_redirect = true;
    }

    private function checkIfDifferentCharacterIdHasBeenProvided(EveUser $user): void
    {
        $step_up_character_id = session()->pull('step_up');

        if (! $step_up_character_id || $step_up_character_id === $user->character_id) {
            return;
        }

        session()->flash('error', 'Please make sure to select the same character to step up on CCP as on seatplus.');
        $this->should_redirect = true;
    }
}
