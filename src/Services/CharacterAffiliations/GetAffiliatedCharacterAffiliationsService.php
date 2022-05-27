<?php

namespace Seatplus\Auth\Services\CharacterAffiliations;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Seatplus\Auth\Services\Dtos\AffiliationsDto;

class GetAffiliatedCharacterAffiliationsService extends GetCharacterAffiliationsServiceBase
{
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
            ->select('character_affiliations.*')
            ->distinct();
    }

    private function getAllowedAffiliatedCharacterAffiliations() : Builder
    {
        $allowed_affiliations = GetAllowedCharacterAffiliationsService::make($this->affiliationsDto)
            ->getQuery();

        return $this->removeForbiddenAffiliations($allowed_affiliations);
    }

    private function getInvertedAffiliatedCharacterAffiliations() : Builder
    {
        $inverse_affiliations = GetInvertedCharacterAffiliationsService::make($this->affiliationsDto)
            ->getQuery();

        return $this->removeForbiddenAffiliations($inverse_affiliations);
    }
}
