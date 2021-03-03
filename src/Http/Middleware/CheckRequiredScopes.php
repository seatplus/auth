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

namespace Seatplus\Auth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Seatplus\Auth\Models\User;
use Seatplus\Auth\Services\BuildCharacterScopesArray;
use Seatplus\Auth\Services\BuildUserLevelRequiredScopes;
use Seatplus\Eveapi\Models\SsoScopes;

class CheckRequiredScopes
{
    private User $user;

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure                 $next
     *
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        $this->buildUser();

        $characters_with_missing_scopes = $this->getCharactersWithMissingScopes();

        return $characters_with_missing_scopes->isEmpty() ? $next($request) : $this->redirectTo($characters_with_missing_scopes);
    }

    public function buildUser(): void
    {
        /** @noinspection PhpFieldAssignmentTypeMismatchInspection */
        $this->user = User::with(
            'characters.alliance.ssoScopes',
            'characters.corporation.ssoScopes',
            'characters.application.corporation.ssoScopes',
            'characters.application.corporation.alliance.ssoScopes',
            'characters.refresh_token',
            'application.corporation.ssoScopes',
            'application.corporation.alliance.ssoScopes'
        )->addSelect(['global_scope' => SsoScopes::global()->select('selected_scopes')])
            ->find(auth()->user()->getAuthIdentifier());
    }

    private function getCharactersWithMissingScopes(): Collection
    {

        // Get user level required scopes
        $user_scopes = BuildUserLevelRequiredScopes::get($this->user);

        //dump(json_decode($this->user->global_scope));

        return $this->user->characters->map(fn ($character) => BuildCharacterScopesArray::get($character, $user_scopes))
            ->filter(fn ($character) => Arr::get($character, 'missing_scopes'));
    }

    /*
     * This method should return the user to a view where he needs to handle the addition of required scopes
     */
    protected function redirectTo(Collection $missing_character_scopes)
    {
        //TODO: extend this with default view.
    }
}
