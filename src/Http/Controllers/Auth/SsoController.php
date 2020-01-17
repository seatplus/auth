<?php

/*
 * MIT License
 *
 * Copyright (c) 2019, 2020 Felix Huber
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
use Seatplus\Auth\Http\Actions\Sso\FindOrCreateUserAction;
use Seatplus\Auth\Http\Actions\Sso\GetSsoScopesAction;
use Seatplus\Auth\Http\Actions\Sso\UpdateRefreshTokenAction;
use Seatplus\Auth\Http\Controllers\Controller;
use Seatplus\Auth\Models\User;

class SsoController extends Controller
{
    /**
     * Redirect the user to the Eve Online authentication page.
     *
     * @param \Laravel\Socialite\Contracts\Factory              $social
     * @param \Seatplus\Web\Http\Actions\Sso\GetSsoScopesAction $get_sso_scopes_action
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function redirectToProvider(Socialite $social, GetSsoScopesAction $get_sso_scopes_action)
    {
        $scopes = $get_sso_scopes_action->execute();

        return $social->driver('eveonline')
            ->scopes($scopes)
            ->redirect();
    }

    /**
     * Obtain the user information from Eve Online.
     *
     * @param \Laravel\Socialite\Contracts\Factory                    $social
     * @param \Seatplus\Web\Http\Actions\Sso\FindOrCreateUserAction   $find_or_create_user_action
     * @param \Seatplus\Web\Http\Actions\Sso\UpdateRefreshTokenAction $update_refresh_token_action
     *
     * @return \Seatplus\Web\Http\Controllers\Auth\Response
     */
    public function handleProviderCallback(
        Socialite $social,
        FindOrCreateUserAction $find_or_create_user_action,
        UpdateRefreshTokenAction $update_refresh_token_action)
    {
        $eve_data = $social->driver('eveonline')->user();

        // Get or create the User bound to this login.
        $user = $find_or_create_user_action->execute($eve_data);

        // Update the refresh token for this character.

        $update_refresh_token_action->execute($eve_data);

        if (!$this->loginUser($user)) {
            return redirect()->route('auth.login')
                ->with('error', 'Login failed. Please contact your administrator.');
        }

        return redirect()->intended();
    }

    /**
     * Login the user.
     *
     * This method returns a boolean as a status flag for the
     * login routine. If a false is returned, it might mean
     * that that account is not allowed to sign in.
     *
     * @param \Seatplus\Web\Models\User $user
     *
     * @return bool
     */
    public function loginUser(User $user): bool
    {

        // Login and "remember" the given user...
        auth()->login($user, true);

        return true;
    }
}
