<?php

namespace Seatplus\Auth\Services\Affiliations;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Seatplus\Auth\Enums\AffiliationType;
use Seatplus\Auth\Services\Dtos\AffiliationsDto;
use Seatplus\Eveapi\Models\Alliance\AllianceInfo;
use Seatplus\Eveapi\Models\Character\CharacterAffiliation;
use function Pest\Laravel\get;

class GetForbiddenAffiliatedIdService extends GetAffiliatedIdsServiceBase
{
    public static function make(AffiliationsDto $affiliationsDto)
    {
        return new static($affiliationsDto);
    }

    public function getQuery() : Builder
    {
        $type = AffiliationType::FORBIDDEN;
        $alias = sprintf('%s_entities', $type->value());

        $owned_character_affiliations = GetOwnedAffiliatedIdsService::make($this->affiliationsDto)
            ->getQuery();

        $affiliation = $this->getAffiliation()->where('type', $type->value())
            /*->whereNotExists(
                fn (QueryBuilder $query) => $query
                    ->select(DB::raw(1))
                    ->fromSub($owned_character_affiliations, 'owned')
                    ->whereColumn('affiliations.affiliatable_id', 'owned.affiliated_id')
            )*/
        ;

        $character_affiliations = CharacterAffiliation::query()
            ->when(
                $affiliation->count(),
                fn (Builder $query) => $query
                    ->joinSub(
                        $affiliation,
                        $alias,
                        fn (JoinClause $join) => $this->joinAffiliatedCharacterAffiliations($join, $alias)
                    ),
                fn (Builder $query) => $query->whereNull('character_affiliations.character_id')
            )
            ->whereNotExists(
                fn (QueryBuilder $query) => $query
                    ->select(DB::raw(1))
                    ->fromSub($owned_character_affiliations, 'owned')
                    ->whereColumn('character_affiliations.character_id', 'owned.affiliated_id')
            )
            ->select('character_affiliations.character_id as forbidden_id');

        $corporation_affiliations = CharacterAffiliation::query()
            ->when(
                $affiliation->count(),
                fn (Builder $query) => $query
                    ->joinSub(
                        $affiliation,
                        $alias,
                        fn (JoinClause $join) => $this->joinAffiliatedCorporationAffiliations($join, $alias)
                    ),
                fn (Builder $query) => $query->whereNull('character_affiliations.corporation_id')
            )
            ->whereNotExists(
                fn (QueryBuilder $query) => $query
                    ->select(DB::raw(1))
                    ->fromSub($owned_character_affiliations, 'owned')
                    ->whereColumn('character_affiliations.corporation_id', 'owned.affiliated_id')
            )
            ->select('character_affiliations.corporation_id as forbidden_id');

        $alliance_affiliations = $affiliation
            ->where('affiliatable_type', AllianceInfo::class)
            ->select('affiliatable_id as forbidden_id');

        return $character_affiliations
            ->union($corporation_affiliations)
            ->union($alliance_affiliations)
            ;
    }
}
