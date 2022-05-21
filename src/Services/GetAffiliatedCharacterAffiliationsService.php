<?php

namespace Seatplus\Auth\Services;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Seatplus\Auth\Enums\AffiliationType;
use Seatplus\Auth\Models\Permissions\Affiliation;
use Seatplus\Auth\Services\Dtos\AffiliationsDto;
use Seatplus\Eveapi\Models\Alliance\AllianceInfo;
use Seatplus\Eveapi\Models\Character\CharacterAffiliation;
use Seatplus\Eveapi\Models\Character\CharacterInfo;
use Seatplus\Eveapi\Models\Corporation\CorporationInfo;

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

    /* private function joinAffiliatedCharacterAffiliations(JoinClause $join, string $alias) : JoinClause
     {
         return $join
             ->on('character_affiliations.character_id', '=', "$alias.affiliatable_id")->where("$alias.affiliatable_type", CharacterInfo::class)
             ->orOn('character_affiliations.corporation_id', '=', "$alias.affiliatable_id")->where("$alias.affiliatable_type", CorporationInfo::class)
             ->orOn('character_affiliations.alliance_id', '=', "$alias.affiliatable_id")->where("$alias.affiliatable_type", AllianceInfo::class);
     }

     /**
      * @return Builder
      */
    /*public function getAffiliation(): Builder
    {
        if (! isset($this->affiliation)) {
            $this->createAffiliation();
        }

        return clone $this->affiliation;
    }

    public function createAffiliation(): void
    {
        $this->affiliation = Affiliation::query()
            ->whereRelation('role.permissions', 'name', $this->affiliationsDto->permission)
            ->whereRelation('role.members', 'user_id', $this->affiliationsDto->user->getAuthIdentifier());
    }*/
}
