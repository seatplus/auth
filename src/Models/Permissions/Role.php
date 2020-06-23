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

namespace Seatplus\Auth\Models\Permissions;

use Illuminate\Support\Collection;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    public function affiliations()
    {
        return $this->hasMany(Affiliation::class, 'role_id');
    }

    /**
     * @return array
     */
    public function getAffiliatedIdsAttribute(): array
    {
        //eager load relations for preventing n+1 queries
        $role_with_relationships = $this->loadMissing([
            'affiliations.affiliatable.characters' => fn ($query) => $query->has('characters')->select('character_infos.character_id'),
        ]);

        return $role_with_relationships->getAffiliatedIds()
            ->diff($role_with_relationships->getForbiddenAndInverseIds()->toArray())
            ->all();
    }

    private function getAffiliatedIds(): Collection
    {
        return $this->affiliations
            ->reject(fn ($affiliation) => $affiliation->type === 'forbidden')
            ->map(fn ($affiliation)    => $affiliation->type === 'allowed' ? $affiliation->character_ids : $affiliation->inverse_character_ids)
            ->flatten()
            ->unique();
    }

    private function getForbiddenAndInverseIds(): Collection
    {
        return $this->affiliations
            // we are only concerned about forbidden and inverse ids
            ->reject(fn ($affiliation) => $affiliation->type === 'allowed')
            ->map(fn ($affiliation)    => $affiliation->character_ids)
            ->flatten()
            ->unique();
    }

    public function delete()
    {
        $this->affiliations()->delete();

        return parent::delete();
    }
}
