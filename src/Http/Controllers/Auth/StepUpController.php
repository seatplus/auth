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
use Seatplus\Auth\Http\Controllers\Controller;
use Seatplus\Eveapi\Models\RefreshToken;

class StepUpController extends Controller
{
    /**
     * Redirect the user to the Eve Online authentication page.
     *
     * @param \Laravel\Socialite\Contracts\Factory      $social
     * @param int                                       $character_id
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function __invoke(Socialite $social, int $character_id)
    {
        if (! $this->isCharacterAssociatedToCurrentUser($character_id)) {
            return redirect()->back()->with('error', 'character must belong to your account');
        }

        $add_scopes = explode(',', request()->query('add_scopes'));

        $scopes = collect(RefreshToken::find($character_id)->scopes)->merge($add_scopes)->toArray();

        session([
            'rurl'       => url()->previous(),
            'sso_scopes' => $scopes,
            'step_up'    => $character_id,
        ]);

        return $social
            ->driver('eveonline')
            ->scopes($scopes)
            ->redirect();
    }

    private function isCharacterAssociatedToCurrentUser(int $character_id): bool
    {
        return auth()->user()->characters->pluck('character_id')->contains($character_id);
    }
}
