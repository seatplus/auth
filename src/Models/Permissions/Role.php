<?php

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
}
