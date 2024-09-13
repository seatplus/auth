<?php

namespace Seatplus\Auth\Http\Controllers\Auth;

use Illuminate\Http\RedirectResponse;
use Laravel\Socialite\Contracts\Factory as Socialite;
use Seatplus\Auth\Containers\EveUser;
use Seatplus\Auth\Http\Actions\Sso\FindOrCreateUserAction;
use Seatplus\Auth\Http\Actions\Sso\UpdateRefreshTokenAction;
use Seatplus\Auth\Jobs\RoleMemberSync;
use Seatplus\Auth\Services\AuthenticationService;
use SocialiteProviders\Manager\OAuth2\User as SocialiteUser;

class CallbackController
{
    private bool $should_redirect = false;

    public function __construct(
        private AuthenticationService $authenticationService
    )
    {
    }

    public function __invoke(
        Socialite $social,
        FindOrCreateUserAction $find_or_create_user_action,
        UpdateRefreshTokenAction $update_refresh_token_action
    ): RedirectResponse {

        /* @var SocialiteUser $socialite_user */
        $socialite_user = $social->driver('eveonline')->user();

        $eve_data = new EveUser(
            character_id: data_get($socialite_user, 'attributes.character_id'),
            character_owner_hash: data_get($socialite_user, 'attributes.character_owner_hash'),
            token: data_get($socialite_user, 'token'),
            refreshToken: data_get($socialite_user, 'refreshToken'),
            expiresIn: data_get($socialite_user, 'expiresIn'),
            user: data_get($socialite_user, 'user'),
        );

        // if return url was set, set the intended URL
        $return_url = session()->pull('rurl');
        if ($return_url) {
            $this->authenticationService->setIntendedUrl($return_url);
        }

        // check if the requested scopes matches the provided scopes
        if ($this->authenticationService->isUserAuthenticated()) {
            $hasNotMatchingSsoScopes = $this->hasNotMatchingSsoScopes($eve_data);
            $isDifferentCharacterIdProvided = $this->isDifferentCharacterIdProvided($eve_data);

            if ($isDifferentCharacterIdProvided || $hasNotMatchingSsoScopes) {
                return redirect()->intended();
            }
        }

        // Get or create the User bound to this login.
        $user = $find_or_create_user_action($eve_data);

        /*
         * Update the refresh token for this character.
         */
        $update_refresh_token_action($eve_data);

        if (! $this->authenticationService->loginUser($user)) {
            return redirect()->back()
                ->with('error', 'Login failed. Please contact your administrator.');
        }

        $this->authenticationService->flashMessage('success', 'Character added/updated successfully');

        RoleMemberSync::dispatch()->onQueue('high');

        return redirect()->intended();
    }

    private function hasNotMatchingSsoScopes(EveUser $user): bool
    {
        $sso_scopes = $this->authenticationService->getSessionValue('sso_scopes');
        $missing_scopes = array_diff($sso_scopes, $user->getScopes());

        if (!empty($missing_scopes)) {
            $this->authenticationService->flashMessage('error', 'Something might have gone wrong. You might have changed the requested scopes on esi, please refer from doing so.');
            return true;
        }

        return false;
    }

    private function isDifferentCharacterIdProvided(EveUser $user): bool
    {
        $step_up_character_id = $this->authenticationService->getSessionValue('step_up');

        if (! $step_up_character_id || $step_up_character_id === $user->character_id) {
            return false;
        }

        $this->authenticationService->flashMessage('error', 'Please make sure to select the same character to step up on CCP as on seatplus.');
        return true;
    }
}
