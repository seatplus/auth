<?php

namespace Seatplus\Auth\Services\Affiliations;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Seatplus\Auth\Enums\AffiliationType;
use Seatplus\Auth\Services\Dtos\AffiliationsDto;
use Seatplus\Eveapi\Models\Alliance\AllianceInfo;
use Seatplus\Eveapi\Models\Character\CharacterAffiliation;
use Seatplus\Eveapi\Models\Corporation\CorporationInfo;

class GetInvertedAffiliatedIdsService extends GetAffiliatedIdsServiceBase
{
    public static function make(AffiliationsDto $affiliationsDto)
    {
        return new static($affiliationsDto);
    }

    public function getQuery() : Builder
    {
        $type = AffiliationType::INVERSE;
        $alias = sprintf('%s_entities', $type->value());

        $affiliation = $this->getAffiliation()->where('type', $type->value());

        $character_affiliations = CharacterAffiliation::query()
            ->when(
                $affiliation->count(),
                fn (Builder $query) => $query
                    ->leftJoinSub(
                        $affiliation,
                        $alias,
                        fn (JoinClause $join) => $this->joinAffiliatedCharacterAffiliations($join, $alias)
                    )
                    ->whereNull("$alias.affiliatable_id"),
                fn (Builder $query) => $query->whereNull('character_id')
            )
            ->select('character_affiliations.character_id as affiliated_id');

        $corporation_affiliations = CharacterAffiliation::query()
            ->when(
                $affiliation->whereIn('affiliatable_type', [CorporationInfo::class, AllianceInfo::class])->count(),
                fn (Builder $query) => $query
                    ->leftJoinSub(
                        $affiliation,
                        $alias,
                        fn (JoinClause $join) => $this->joinAffiliatedCorporationAffiliations($join, $alias)
                    )
                    ->whereNull("$alias.affiliatable_id"),
                fn (Builder $query) => $query->whereNull('corporation_id')
            )
            ->select('character_affiliations.corporation_id as affiliated_id');

        $alliance_affiliations = CharacterAffiliation::query()
            ->when(
                $affiliation->where('affiliatable_type', AllianceInfo::class)->count(),
                fn (Builder $query) => $query
                    ->leftJoinSub(
                        $affiliation,
                        $alias,
                        'character_affiliations.alliance_id', '=', "$alias.affiliatable_id"
                    )
                    ->whereNull("$alias.affiliatable_id"),
                fn (Builder $query) => $query->whereNull('alliance_id')
            )
            ->select('character_affiliations.alliance_id as affiliated_id');

        return $character_affiliations
            ->union($corporation_affiliations)
            ->union($alliance_affiliations);
    }
}
