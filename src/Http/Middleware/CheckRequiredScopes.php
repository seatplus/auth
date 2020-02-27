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
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;

class CheckRequiredScopes
{
    /**
     * @var \Illuminate\Support\Collection
     */
    private $required_scopes;

    /**
     * @var \Illuminate\Support\Collection
     */
    private $missing_character_scopes;

    public function __construct()
    {
        $this->required_scopes = collect();
        $this->missing_character_scopes = collect();
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
        $characters = $this->charactersWithRequiredSsoScopes();

        if ($characters->isEmpty()) {
            return $next($request);
        }

        $this->buildRequiredScopes($characters);

        $this->buildDifferences();

        if ($this->getMissingcharacterscopes()->isNotEmpty()) {
            return $this->redirectTo($this->getMissingcharacterscopes());
        }

        return $next($request);
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function getMissingcharacterscopes(): \Illuminate\Support\Collection
    {
        return $this->missing_character_scopes;
    }

    /**
     * @return \Illuminate\Support\Collection
     */
    public function getRequiredScopes(): \Illuminate\Support\Collection
    {
        return $this->required_scopes->unique();
    }

    private function charactersWithRequiredSsoScopes(): Collection
    {
        return auth()->user()->characters()->has('alliance.ssoScopes')
            ->orHas('corporation.ssoScopes')
            ->with('alliance.ssoScopes',
                'corporation.ssoScopes',
                'application.corporation.ssoScopes',
                'application.corporation.alliance.ssoScopes'
            )
            ->get();
    }

    private function buildRequiredScopes(Collection $characters)
    {
        $characters->map(function ($character) {
            return [
                'corporation_scopes'             => $character->corporation->ssoScopes->selected_scopes ?? [],
                'alliance_scopes'                => $character->alliance->ssoScopes->selected_scopes ?? [],
                'application_corporation_scopes' => $character->application->corporation->ssoScopes->selected_scopes ?? [],
                'application_alliance_scopes'    => $character->application->corporation->alliance->ssoScopes->selected_scopes ?? [],
            ];
        })
        ->flatten(1)
        ->filter()
        ->each(function ($scope_array) {
            collect($scope_array)
                ->flatten()
                ->map(fn ($scope) => explode(',', $scope))
                ->flatten()
                ->each(fn ($scope) => $this->required_scopes->push($scope));

            if (Arr::get($scope_array, 'corporation')) {
                $this->required_scopes->push('esi-characters.read_corporation_roles.v1');
            }
        });
    }

    private function buildDifferences()
    {
        $this->missing_character_scopes = auth()->user()
            ->characters
            ->reject(fn ($character) => empty(array_diff($this->getRequiredScopes()->toArray(), $character->refresh_token->scopes)))
            ->map(fn ($character)    => ['character' => $character, 'missing_scopes' => array_diff($this->getRequiredScopes()->toArray(), $character->refresh_token->scopes)]);
    }

    /*
     * This method should return the user to a view where he needs to handle the addition of required scopes
     */
    protected function redirectTo(\Illuminate\Support\Collection $missing_character_scopes)
    {
        //TODO: extend this with default view.
    }
}
