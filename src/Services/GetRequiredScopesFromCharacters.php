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

namespace Seatplus\Auth\Services;

use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class GetRequiredScopesFromCharacters
{
    private Collection $required_scopes;

    public function execute(Collection $characters): Collection
    {
        $this->required_scopes = collect();

        $characters->map(function ($character) {
            return [
                'corporation_scopes'             => $character->corporation->ssoScopes->selected_scopes ?? [],
                'alliance_scopes'                => $character->alliance->ssoScopes->selected_scopes ?? [],
                'application_corporation_scopes' => $character->application->corporation->ssoScopes->selected_scopes ?? [],
                'application_alliance_scopes'    => $character->application->corporation->alliance->ssoScopes->selected_scopes ?? [],
                'global_scopes'                  => setting('global_sso_scopes') ?? [],
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

        return $this->required_scopes->unique();
    }
}
