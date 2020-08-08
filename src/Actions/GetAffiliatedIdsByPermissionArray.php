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

namespace Seatplus\Auth\Actions;

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Seatplus\Auth\Models\User;
use Seatplus\Eveapi\Models\Alliance\AllianceInfo;
use Seatplus\Eveapi\Models\Character\CharacterInfo;
use Seatplus\Eveapi\Models\Corporation\CorporationInfo;

class GetAffiliatedIdsByPermissionArray
{
    private $permission;

    /**
     * @var \Illuminate\Contracts\Auth\Authenticatable|null
     */
    private $user;

    /**
     * @var string
     */
    private $cache_key;

    private string $corporation_role;

    /**
     * @return string
     */
    public function getCacheKey(): string
    {
        return $this->cache_key;
    }

    public function __construct(string $permission, string $corporation_role = '')
    {
        $this->permission = $permission;
        $this->user = auth()->user();
        $this->cache_key = sprintf('affiliated character ids by permission %s for user wit user_id: %s',
            $this->user->id, $this->permission);

        $this->corporation_role = $corporation_role;
    }

    public function execute(): array
    {
        try {
            return cache($this->cache_key) ?? $this->getResult();
        } catch (\Exception $e) {
            throw $e;
        }

        return ['error'];
    }

    /**
     * @throws \Exception
     *
     * @return array
     */
    private function getResult(): array
    {
        $affiliated_ids = $this->getAffiliatedIds()->toArray();

        cache([$this->cache_key => $affiliated_ids], now()->addMinutes(5));

        return $affiliated_ids;
    }

    private function getAffiliatedIds(): Collection
    {
        if ($this->user->can('superuser')) {
            return $this->getAllCharacterAndCorporationIds();
        }

        $user = User::with(
            [
                'roles.permissions',
                //'roles.affiliations.affiliatable.characters' => fn ($query) => $query->has('characters')->select('character_infos.character_id'),
                'roles.affiliations.affiliatable' => fn (MorphTo $morph_to) => $morph_to->morphWith([CorporationInfo::class => 'characters', AllianceInfo::class => ['characters', 'corporations']]),
            ]
        )->whereHas('roles.permissions', function ($query) {
            $query->where('name', $this->permission);
        })
            ->where('id', $this->user->id)
            ->first();

        // if authenticated user has no roles, make sure to skip the roles access
        $affiliated_ids = ! $user ? collect() : $user->roles->map(fn ($role) => $role->affiliated_ids);

        // before returning add the owned character ids
        return $affiliated_ids->merge($this->buildOwnedIds())
            ->flatten()
            ->unique();
    }

    private function getAllCharacterAndCorporationIds(): Collection
    {
        $all_ids = collect();

        CharacterInfo::query()->cursor()->each(fn ($character) => $all_ids->push($character->character_id));
        CorporationInfo::query()->cursor()->each(fn ($corporation) => $all_ids->push($corporation->corporation_id));

        return $all_ids;
    }

    private function buildOwnedIds(): Collection
    {
        //$this->user->characters->pluck('character_id');

        return User::whereId($this->user->getAuthIdentifier())
            ->with('characters.roles', 'characters.corporation')
            ->get()
            ->whenNotEmpty(fn ($collection) => $collection
                ->first()
                ->characters
                ->map(fn ($character) => [$this->getCorporationId($character), $character->character_id])
                ->flatten()
                ->filter()
            )
            ->flatten()->unique();
    }

    private function getCorporationId(CharacterInfo $character)
    {
        if (! $this->corporation_role || ! $character->roles) {
            return null;
        }

        return $character->roles->hasRole('roles', Str::ucfirst($this->corporation_role)) ? $character->corporation->corporation_id : null;
    }
}
