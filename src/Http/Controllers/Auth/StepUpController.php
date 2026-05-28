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
use Seatplus\Auth\Models\User;
use Seatplus\Eveapi\Models\RefreshToken;
use SocialiteProviders\Eveonline\Provider;
use Symfony\Component\HttpFoundation\RedirectResponse;

class StepUpController extends Controller
{
    /**
     * Redirect the user to the Eve Online authentication page.
     */
    public function __invoke(Socialite $socialite, int $character_id): RedirectResponse
    {
        if (! $this->isCharacterAssociatedToCurrentUser($character_id)) {
            return redirect()->back()->with('error', 'character must belong to your account');
        }

        $add_scopes = explode(',', request()->query('add_scopes'));

        $token = RefreshToken::find($character_id);
        $scopes = collect($token !== null ? $token->scopes : [])->merge($add_scopes)->toArray();

        session([
            'rurl' => url()->previous(),
            'sso_scopes' => $scopes,
            'step_up' => $character_id,
        ]);

        $driver = $socialite->driver('eveonline');

        /** @var Provider $driver */
        return $driver->scopes($scopes)->redirect();
    }

    private function isCharacterAssociatedToCurrentUser(int $character_id): bool
    {
        $user = User::query()->find(auth()->user()->getAuthIdentifier());

        return $user->characters->pluck('character_id')->contains($character_id);
    }
}
