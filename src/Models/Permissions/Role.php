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
use Seatplus\Eveapi\Models\Character\CharacterAffiliation;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    private $affiliated_ids;

    /**
     * @var \Illuminate\Support\Collection
     */
    private $ids_to_remove;

    public function affiliations()
    {
        return $this->hasMany(Affiliation::class, 'role_id');
    }

    /**
     * @param int $affiliation_id
     *
     * @return bool
     */
    public function isAffiliated(int $affiliation_id): bool
    {
        $affiliated_ids = $this->buildAffiliatedIds()->getAffiliatedIds();

        return in_array($affiliation_id, $affiliated_ids->toArray());
    }

    private function buildAffiliatedCharacterIds(): void
    {
        $this->affiliations()
            ->get()
            ->filter()
            ->pipe(function ($collection) {
                $collection->filter(function (Affiliation $affiliation) {
                    return $affiliation->type === 'inverse';
                })->each(function (Affiliation $affiliation) {
                    $affiliation->characterAffiliations->each(function (CharacterAffiliation $character_affiliation) {
                        $this->affiliated_ids->push($character_affiliation->character_id);
                        $this->ids_to_remove->push($character_affiliation->id_to_remove);
                    });
                });

                return $collection;
            })
            ->pipe(function ($collection) {
                $collection->filter(function (Affiliation $affiliation) {
                    return $affiliation->type === 'allowed';
                })->each(function (Affiliation $affiliation) {
                    $affiliation->characterAffiliations->each(function (CharacterAffiliation $character_affiliation) {
                        $this->affiliated_ids->push($character_affiliation->character_id);
                    });
                });

                return $collection;
            })
            ->pipe(function ($collection) {
                $collection->filter(function (Affiliation $affiliation) {
                    return $affiliation->type === 'forbidden';
                })->each(function (Affiliation $affiliation) {
                    $affiliation->characterAffiliations->each(function (CharacterAffiliation $character_affiliation) {
                        $this->ids_to_remove->push($character_affiliation->character_id);
                    });
                });

                return $collection;
            });
    }

    public function buildAffiliatedIds()
    {
        $this->affiliated_ids = collect();
        $this->ids_to_remove = collect();

        $this->buildAffiliatedCharacterIds();

        //TODO dump('affiliate_ids', $this->affiliated_ids->unique(), 'remove', $this->ids_to_remove->unique());

        $this->affiliated_ids = $this->affiliated_ids->unique()->diff($this->ids_to_remove->unique());

        return $this;
    }

    /**
     * @return mixed
     */
    public function getAffiliatedIds(): Collection
    {
        return $this->affiliated_ids;
    }

    public function delete()
    {
        $this->affiliations()->delete();

        return parent::delete();
    }
}
