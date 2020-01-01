<?php

namespace Seatplus\Auth\Models\Permissions;

use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    private $affiliated_ids;

    public function affiliations()
    {
        return $this->hasMany(Affiliation::class, 'role_id');
    }

    /**
     * @param int $affiliation_id
     *
     * @return bool
     */
    public function isAffiliated(int $affiliation_id) : bool
    {
        $this->affiliated_ids = collect();

        $this->affiliations()
            ->allowedAffiliatedCharacterIds()
            ->affiliatedCharacterIdsThroughInverse()
            ->InvertedCharacterIdsThroughInverse()
            ->forbiddenAffiliatedCharacterIds()
            ->get()
            ->filter()
            ->pipe(function ($collection) {

                $collection->pluck('affiliated_character_ids_through_inverse')
                    ->filter()
                    ->unique()
                    ->each(function ($character_id) {
                        $this->affiliated_ids->push($character_id);
                    });

                $inverted_ids = $collection->pluck('inverted_character_ids_through_inverse')
                    ->filter()
                    ->unique();

                $this->affiliated_ids = $this->affiliated_ids->diff($inverted_ids);

                return $collection;
            })
            ->pipe(function ($collection) {

                $collection->pluck('affiliated_character_ids_through_allowed')
                    ->filter()
                    ->unique()
                    ->each(function ($character_id) {
                        $this->affiliated_ids->push($character_id);
                    });

                return $collection;
            })
            ->pipe(function ($collection) {

                $forbidden_ids = $collection->pluck('forbidden_character_ids')
                    ->filter()
                    ->unique();

                if($forbidden_ids->isNotEmpty())
                    $this->affiliated_ids = $this->affiliated_ids->diff($forbidden_ids);

                return $collection;
            })
        ;

        return in_array($affiliation_id, $this->affiliated_ids->toArray());

    }
}
