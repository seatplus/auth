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
use Seatplus\Auth\Models\CharacterUser;
use Seatplus\Eveapi\Models\Character\CharacterInfo;

class BuildCharacterScopesArray
{
    public static function get(CharacterInfo $character, array $user_scopes = []): array
    {
        $user_scopes = $user_scopes
            ? $user_scopes
            : (optional(CharacterUser::whereCharacterId($character->character_id)->first())->user
                ? BuildUserLevelRequiredScopes::get(CharacterUser::whereCharacterId($character->character_id)->first()->user)
                : []
            );

        $character_array = [
            'character' => $character,
            'required_scopes' => collect([
                'corporation_scopes'             => $character->corporation->ssoScopes->selected_scopes ?? [],
                'alliance_scopes'                => $character->alliance->ssoScopes->selected_scopes ?? [],
                'character_application_corporation_scopes' => $character->application->corporation->ssoScopes->selected_scopes ?? [],
                'character_application_alliance_scopes'    => $character->application->corporation->alliance->ssoScopes->selected_scopes ?? [],
                'user_scope'                    => $user_scopes,
            ])->flatten(1)
                ->filter()
                ->unique()
                ->flatten(1)
                ->toArray(),
            'token_scopes' => $character->refresh_token->scopes ?? [],
        ];

        $required_scopes = Arr::get($character_array, 'required_scopes');
        $token_scopes = Arr::get($character_array, 'token_scopes');
        $missing_scopes = collect($required_scopes)
            ->reject(fn ($required_scope) => in_array($required_scope, $token_scopes))
            ->toArray();

        return Arr::add($character_array, 'missing_scopes', $missing_scopes);
    }
}
