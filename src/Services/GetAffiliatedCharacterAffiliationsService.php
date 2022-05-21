<?php

namespace Seatplus\Auth\Services;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Seatplus\Auth\Enums\AffiliationType;
use Seatplus\Auth\Services\Dtos\AffiliationsDto;
use Seatplus\Eveapi\Models\Character\CharacterAffiliation;

class GetAffiliatedCharacterAffiliationsService extends GetCharacterAffiliationsServiceBase
{

    //private Builder $affiliation;

    public static function make(AffiliationsDto $affiliationsDto)
    {
        return new static($affiliationsDto);
    }

    public function getQuery() : Builder
    {
        $allowed = $this->getAllowedAffiliatedCharacterAffiliations();
        $inverted = $this->getInvertedAffiliatedCharacterAffiliations();

        return $allowed
            ->union($inverted)
            ->select('character_affiliations.*');
    }

    private function getAllowedAffiliatedCharacterAffiliations() : Builder
    {
        $type = AffiliationType::ALLOWED;
        $alias = sprintf('%s_entities', $type->value());

        $affiliation = $this->getAffiliation()->where('type', $type->value());

        return CharacterAffiliation::query()
            ->joinSub(
                $affiliation,
                $alias,
                fn (JoinClause $join) => $this->joinAffiliatedCharacterAffiliations($join, $alias)
            )
            ->select('character_affiliations.*')
            ;
    }

    private function getInvertedAffiliatedCharacterAffiliations() : Builder
    {
        $type = AffiliationType::INVERSE;
        $alias = sprintf('%s_entities', $type->value());

        $affiliation = $this->getAffiliation()->where('type', $type->value());

        return CharacterAffiliation::query()
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
            ->select('character_affiliations.*')
            ;
    }
}
