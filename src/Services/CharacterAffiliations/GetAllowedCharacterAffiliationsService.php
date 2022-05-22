<?php

namespace Seatplus\Auth\Services\CharacterAffiliations;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Seatplus\Auth\Enums\AffiliationType;
use Seatplus\Auth\Services\Dtos\AffiliationsDto;
use Seatplus\Eveapi\Models\Character\CharacterAffiliation;

class GetAllowedCharacterAffiliationsService extends GetCharacterAffiliationsServiceBase
{
    public static function make(AffiliationsDto $affiliationsDto)
    {
        return new static($affiliationsDto);
    }

    public function getQuery() : Builder
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
            ->select('character_affiliations.*');
    }
}
