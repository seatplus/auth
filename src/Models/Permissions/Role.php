<?php

namespace Seatplus\Auth\Models\Permissions;

use Illuminate\Support\Arr;
use Seatplus\Eveapi\Models\Character\CharacterInfo;
use Seatplus\Eveapi\Models\Corporation\CorporationInfo;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    private $affiliated_ids;

    public function affiliations()
    {

        return $this->hasOne(Affiliation::class, 'role_id');
    }

    /**
     * @param int $affiliation_id
     *
     * @return bool
     */
    public function isAffiliated(int $affiliation_id) : bool
    {

        $this->affiliated_ids = collect($affiliation_id);

        $this->getAffiliatedIds();

        if($this->isInForbiddenAffiliation())
            return false;

        if($this->isInAllowedAffiliation())
            return true;

        if(! $this->isInInverseAffiliation())
            return true;

        return false;
    }

    private function isInForbiddenAffiliation() : bool
    {

        return $this->isInAffiliatedArray($this->affiliations->forbidden);
    }

    private function isInAllowedAffiliation(): bool
    {

        return $this->isInAffiliatedArray($this->affiliations->allowed);
    }

    private function isInInverseAffiliation() : bool
    {
        return is_null($this->affiliations->inverse)
            ? true
            : $this->isInAffiliatedArray($this->affiliations->inverse);
    }

    /**
     * @param array|null $affiliation_ids
     *
     * @return bool
     */
    private function isInAffiliatedArray(?array $affiliation_ids) : bool
    {
        $resut = empty($affiliation_ids) ?
            null :
            array_intersect(
                $this->affiliated_ids->toArray(),
                Arr::flatten($affiliation_ids)
            );

        return ! empty($resut);
    }

    private function getAffiliatedIds() : void
    {
        $id = $this->affiliated_ids->last();

        if ($char = CharacterInfo::find($id))
            $this->affiliated_ids->put('corporation_id', $char->corporation_id);

        $corp = CorporationInfo::find($id);

        if ($this->affiliated_ids->has('corporation_id') && is_null($corp))
            $this->affiliated_ids
                ->put(
                    'alliance_id',
                    (int) CorporationInfo::find($this->affiliated_ids->get('corporation_id'))->alliance_id);
    }
}
