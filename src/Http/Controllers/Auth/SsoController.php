<?php

/*
 * MIT License
 *
 * Copyright (c) 2019, 2020, 2021 Felix Huber
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace Seatplus\Auth\Http\Controllers\Auth;

use Laravel\Socialite\Contracts\Factory as Socialite;
use Seatplus\Auth\Containers\EveUser;
use Seatplus\Auth\Http\Actions\Sso\FindOrCreateUserAction;
use Seatplus\Auth\Http\Actions\Sso\UpdateRefreshTokenAction;
use Seatplus\Auth\Http\Controllers\Controller;
use Seatplus\Auth\Jobs\UserRolesSync;
use Seatplus\Auth\Models\User;
use Seatplus\Auth\Services\GetRequiredScopes;

class SsoController extends Controller
{
    private bool $should_redirect = false;

    /**
     * Redirect the user to the Eve Online authentication page.
     *
     * @param  \Laravel\Socialite\Contracts\Factory  $social
     * @param  \Seatplus\Auth\Services\GetRequiredScopes  $required_scopes
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function redirectToProvider(Socialite $social, GetRequiredScopes $required_scopes)
    {
        $scopes = $required_scopes->execute()->toArray();

        session([
            'rurl' => session()->previousUrl(),
            'sso_scopes' => $scopes,
        ]);

        return $social->driver('eveonline')
            ->scopes($scopes)
            ->redirect();
    }

    /**
     * Obtain the user information from Eve Online.
     *
     * @param  \Laravel\Socialite\Contracts\Factory  $social
     * @param  \Seatplus\Auth\Http\Actions\Sso\FindOrCreateUserAction  $find_or_create_user_action
     * @param  \Seatplus\Auth\Http\Actions\Sso\UpdateRefreshTokenAction  $update_refresh_token_action
     * @return \Illuminate\Http\RedirectResponse
     */
    public function handleProviderCallback(
        Socialite $social,
        FindOrCreateUserAction $find_or_create_user_action,
        UpdateRefreshTokenAction $update_refresh_token_action
    ) {
        $socialite_user = $social->driver('eveonline')->user();
        $rurl = session()->pull('rurl');

        /** @noinspection PhpUndefinedFieldInspection */
        $eve_data = new EveUser(
            character_id: $socialite_user->character_id,
            character_owner_hash: $socialite_user->character_owner_hash,
            token: $socialite_user->token,
            refreshToken: $socialite_user->refreshToken,
            expiresIn: $socialite_user->expiresIn,
            user: $socialite_user->user,
        );

        // if return url was set, set the intended URL
        if ($rurl) {
            redirect()->setIntendedUrl($rurl);
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
     *
     * @param  \Seatplus\Auth\Models\User  $user
     * @return bool
     */
    public function loginUser(User $user): bool
    {
        // Login and "remember" the given user...
        auth()->login($user, true);

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
