<?php

namespace Seatplus\Auth\Services;

use Illuminate\Support\Facades\DB;
use Seatplus\Auth\Enums\AffiliationType;
use Seatplus\Auth\Services\Dtos\AffiliationsDto;
use Seatplus\Eveapi\Models\Character\CharacterAffiliation;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;

class GetForbiddenCharacterAffiliationsService extends GetCharacterAffiliationsServiceBase
{
    public static function make(AffiliationsDto $affiliationsDto)
    {
        return new static($affiliationsDto);
    }

    public function getQuery() : Builder
    {

        $type = AffiliationType::FORBIDDEN;
        $alias = sprintf('%s_entities', $type->value());

        $owned_character_affiliations = GetOwnedCharacterAffiliationsService::make($this->affiliationsDto)
            ->getQuery();

        $affiliation = $this->getAffiliation()->where('type', $type->value())
            ->whereNotExists(
                fn (QueryBuilder $query) => $query
                    ->select(DB::raw(1))
                    ->fromSub($owned_character_affiliations, 'owned')
                    ->whereColumn('affiliations.affiliatable_id', 'owned.character_id')
            )
        ;

        return CharacterAffiliation::query()
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
            ->select('character_affiliations.*')
            ;
    }

}