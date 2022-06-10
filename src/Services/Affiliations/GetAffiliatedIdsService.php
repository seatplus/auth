<?php

namespace Seatplus\Auth\Services\Affiliations;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Seatplus\Auth\Services\Dtos\AffiliationsDto;
use Illuminate\Database\Query\Builder as QueryBuilder;

class GetAffiliatedIdsService extends GetAffiliatedIdsServiceBase
{
    public static function make(AffiliationsDto $affiliationsDto)
    {
        return new static($affiliationsDto);
    }

    public function getQuery() : QueryBuilder
    {
        $allowed = $this->getAllowedAffiliatedCharacterAffiliations();
        $inverted = $this->getInvertedAffiliatedCharacterAffiliations();

        return $allowed
            ->union($inverted)
            ->distinct();
    }

    private function getAllowedAffiliatedCharacterAffiliations() : QueryBuilder
    {
        $allowed_affiliations = GetAllowedAffiliatedIdsService::make($this->affiliationsDto)
            ->getQuery()
        ;

        return $this->removeForbiddenAffiliations($allowed_affiliations);
    }

    private function getInvertedAffiliatedCharacterAffiliations() : QueryBuilder
    {
        $inverse_affiliations = GetInvertedAffiliatedIdsService::make($this->affiliationsDto)
            ->getQuery();

        return $this->removeForbiddenAffiliations($inverse_affiliations);
    }
}
