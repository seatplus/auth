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

namespace Seatplus\Auth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Seatplus\Auth\Models\User;

class CheckRequiredScopes
{

    private User $user;

    public function __construct()
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
        )->find(auth()->user()->getAuthIdentifier());
    }

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

        $characters_with_missing_scopes = $this->getCharactersWithMissingScopes();

        return $characters_with_missing_scopes->isEmpty() ? $next($request) : $this->redirectTo($characters_with_missing_scopes);
    }

    private function getCharactersWithMissingScopes() : Collection
    {

        // Add global required scopes
        $global_scope = setting('global_sso_scopes');

        return $this->user->characters->map(fn ($character) => [
            'character' => $character,
            'required_scopes' => collect([
                'corporation_scopes'             => $character->corporation->ssoScopes->selected_scopes ?? [],
                'alliance_scopes'                => $character->alliance->ssoScopes->selected_scopes ?? [],
                'character_application_corporation_scopes' => $character->application->corporation->ssoScopes->selected_scopes ?? [],
                'character_application_alliance_scopes'    => $character->application->corporation->alliance->ssoScopes->selected_scopes ?? [],
                'global_scopes'                  => $global_scope,
                'user_application_corporation_scopes' => $this->user->application->corporation->ssoScopes->selected_scopes ?? [],
                'user_application_alliance_scopes'    => $this->user->application->corporation->alliance->ssoScopes->selected_scopes ?? [],
            ])->flatten(1)
                ->filter()
                ->unique()
                ->flatten(1)
                ->toArray(),
            'token_scopes' => $character->refresh_token->scopes ?? [],
            ])
            // Build missing scopes
            ->map(fn($character) => Arr::add($character,'missing_scopes',array_diff(Arr::get($character,'required_scopes'), Arr::get($character,'token_scopes'))))
            ->filter(fn($character) => Arr::get($character,'missing_scopes'));
    }

    /*
     * This method should return the user to a view where he needs to handle the addition of required scopes
     */
    protected function redirectTo(Collection $missing_character_scopes)
    {
        //TODO: extend this with default view.
    }
}
