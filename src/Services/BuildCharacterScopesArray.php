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

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Arr;
use Seatplus\Auth\Models\User;
use Seatplus\Eveapi\Models\Character\CharacterInfo;
use Seatplus\Eveapi\Models\SsoScopes;

class BuildCharacterScopesArray
{
    private array $user_scopes;
    private CharacterInfo $character;
    private bool $withUserScope = false;

    /**
     * @return array
     */
    public function getUserScopes(): array
    {
        if(! $this->withUserScope) {
            return [];
        }

        return $this->user_scopes;
    }

    /**
     * @return CharacterInfo
     */
    public function getCharacter(): CharacterInfo
    {
        return $this->character;
    }

    public static function make()
    {
        return new static();
    }

    public function setUserScopes(array $user_scopes)
    {
        $this->withUserScope = true;
        $this->user_scopes = $user_scopes;

        return $this;
    }

    public function setCharacter(CharacterInfo $character) : self
    {
        $this->character = $character;

        return $this;
    }

    public function get(): array
    {

        $character_array = [
            'character' => $this->getCharacter(),
            'required_scopes' => collect([
                'corporation_scopes'             => $this->getCharacter()->corporation->ssoScopes->selected_scopes ?? [],
                'alliance_scopes'                => $this->getCharacter()->alliance->ssoScopes->selected_scopes ?? [],
                'character_application_corporation_scopes' => $this->getCharacter()->application->corporation->ssoScopes->selected_scopes ?? [],
                'character_application_alliance_scopes'    => $this->getCharacter()->application->corporation->alliance->ssoScopes->selected_scopes ?? [],
                'user_scope'                    => $this->getUserScopes(),
            ])->flatten(1)
                ->filter()
                ->unique()
                ->flatten(1)
                ->toArray(),
            'token_scopes' => $this->getCharacter()->refresh_token->scopes ?? [],
        ];

        $required_scopes = Arr::get($character_array, 'required_scopes');
        $token_scopes = Arr::get($character_array, 'token_scopes');
        $missing_scopes = collect($required_scopes)
            ->reject(fn ($required_scope) => in_array($required_scope, $token_scopes))
            ->toArray();

        return Arr::add($character_array, 'missing_scopes', $missing_scopes);
    }
}
