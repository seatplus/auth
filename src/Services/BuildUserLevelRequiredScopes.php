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

namespace Seatplus\Auth\Services;

use Illuminate\Support\Arr;
use Seatplus\Auth\Models\User;
use Seatplus\Eveapi\Models\Character\CharacterInfo;
use Seatplus\Eveapi\Models\SsoScopes;

class BuildUserLevelRequiredScopes
{
    public static function get(User $user): array
    {
        $user = $user->replicate();

        if (! Arr::has($user->getAttributes(), 'global_scope')) {
            $user->global_scope = self::getSelectedScopes();
        }

        return $user
            ->characters
            ->map(fn (CharacterInfo $character) => collect([
                $character->corporation->ssoScopes ?? [],
                $character->alliance->ssoScopes ?? [],
            ])->where('type', 'user'))
            ->filter(fn ($character) => $character->isNotEmpty())
            ->map(fn ($character) => $character->map(fn ($scope) => [
                $scope->selected_scopes,
            ]))
            ->concat([
                'user_application_corporation_scopes' => $user->getRelation('application') ? $user->application->corporation->ssoScopes?->selected_scopes : [],
                'user_application_alliance_scopes' => $user->getRelation('application') ? $user->application->corporation->alliance?->ssoScopes?->selected_scopes : [],
                'global_scopes' => is_array($user->global_scope) ? $user->global_scope : (is_string($user->global_scope) ? json_decode($user->global_scope) : []),
            ])
            ->flatten()
            ->unique()
            ->toArray();
    }

    private static function getSelectedScopes(): array
    {
        $query_result = SsoScopes::global()->select('selected_scopes')->first();

        return $query_result ? $query_result->selected_scopes : [];
    }
}
