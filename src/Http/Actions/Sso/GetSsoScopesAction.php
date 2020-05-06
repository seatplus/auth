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

namespace Seatplus\Auth\Http\Actions\Sso;

use Seatplus\Eveapi\Models\RefreshToken;

class GetSsoScopesAction
{
    private $scopes_to_add;

    public function execute(?int $character_id = null, array $scopes_to_add = []) : array
    {
        $this->scopes_to_add = $scopes_to_add;

        if ($this->plausibilityCheck($character_id)) {
            return $this->addScopesForCharacter($character_id);
        }

        return array_merge(config('eveapi.scopes.minimum'), $this->scopes_to_add);
    }

    private function plausibilityCheck(?int $character_id): bool
    {
        if (is_null($character_id)) {
            return false;
        }

        if (auth()->user() && $this->scopes_to_add && $this->characterIsInUserGroup($character_id)) {
            return true;
        }

        return false;
    }

    private function addScopesForCharacter(int $character_id): array
    {
        return array_merge(RefreshToken::find($character_id)->scopes, $this->scopes_to_add);
    }

    private function characterIsInUserGroup(int $character_id): bool
    {
        return in_array($character_id, auth()->user()->characters->pluck('character_id')->toArray());
    }
}
