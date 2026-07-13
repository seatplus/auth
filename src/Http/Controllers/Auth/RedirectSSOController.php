<?php

declare(strict_types=1);

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
use Seatplus\Auth\Http\Controllers\Controller;
use Seatplus\Auth\Services\AuthenticationService;
use Seatplus\Auth\Services\SsoScopes\GlobalSsoScopesService;
use SocialiteProviders\Eveonline\Provider;
use Symfony\Component\HttpFoundation\RedirectResponse;

class RedirectSSOController extends Controller
{
    public function __construct(
        private readonly GlobalSsoScopesService $service,
        private readonly AuthenticationService $authenticationService
    ) {}

    /**
     * Redirect the user to the Eve Online authentication page.
     *
     * @throws \Throwable
     */
    public function __invoke(Socialite $socialite): RedirectResponse
    {
        // Note: authenticated users are intentionally NOT short-circuited here — hitting this route
        // while logged in is the "add another character" flow. CallbackController links the newly
        // authenticated character onto the current user. Bouncing authenticated users home broke it.
        $scopes = $this->getScopes();

        session([
            'rurl' => $this->authenticationService->getPreviousUrl(),
            'sso_scopes' => $scopes,
        ]);

        $driver = $socialite->driver('eveonline');

        /** @var Provider $driver */
        return $driver->scopes($scopes)->redirect();
    }

    private function getScopes(): array
    {
        $global_scopes = $this->service->get();

        return collect(config('eveapi.scopes.minimum'))
            ->merge($global_scopes)
            ->unique()
            ->filter()
            ->all();
    }
}
