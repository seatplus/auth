<?php

namespace Seatplus\Auth\Services\Affiliations;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Seatplus\Auth\Enums\AffiliationType;
use Seatplus\Auth\Services\Dtos\AffiliationsDto;
use Seatplus\Eveapi\Models\Alliance\AllianceInfo;
use Seatplus\Eveapi\Models\Character\CharacterAffiliation;

class GetAllowedAffiliatedIdsService extends GetAffiliatedIdsServiceBase
{
    public static function make(AffiliationsDto $affiliationsDto)
    {
        return new static($affiliationsDto);
    }

    public function getQuery() : Builder
    {
        $type = AffiliationType::ALLOWED;
        $alias = sprintf('%s_entities', $type->value());

        $affiliation = $this->getAffiliations()->where('type', $type->value());

        $character_affiliations = CharacterAffiliation::query()
            ->joinSub(
                $affiliation,
                $alias,
                fn (JoinClause $join) => $this->joinAffiliatedCharacterAffiliations($join, $alias)
            )
            ->select('character_affiliations.character_id as affiliated_id');

        $corporation_affiliations = CharacterAffiliation::query()
            ->joinSub(
                $affiliation,
                $alias,
                fn (JoinClause $join) => $this->joinAffiliatedCorporationAffiliations($join, $alias)
            )
            ->select('character_affiliations.corporation_id as affiliated_id');

        $alliance_affiliations = $affiliation
            ->where('affiliatable_type', AllianceInfo::class)
            ->select('affiliatable_id as affiliated_id');

        return $character_affiliations
            ->union($corporation_affiliations)
            ->union($alliance_affiliations);
    }
}
