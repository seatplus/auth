<?php

namespace Seatplus\Auth\Services;

use Illuminate\Database\Query\JoinClause;
use Seatplus\Auth\Models\Permissions\Affiliation;
use Seatplus\Auth\Services\Dtos\AffiliationsDto;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Seatplus\Eveapi\Models\Alliance\AllianceInfo;
use Seatplus\Eveapi\Models\Character\CharacterInfo;
use Seatplus\Eveapi\Models\Corporation\CorporationInfo;

class GetCharacterAffiliationsServiceBase
{

    private Builder $affiliation;

    public function __construct(
        protected AffiliationsDto $affiliationsDto
    )
    {
    }

    protected function joinAffiliatedCharacterAffiliations(JoinClause $join, string $alias) : JoinClause
    {
        return $join
            ->on('character_affiliations.character_id', '=', "$alias.affiliatable_id")->where("$alias.affiliatable_type", CharacterInfo::class)
            ->orOn('character_affiliations.corporation_id', '=', "$alias.affiliatable_id")->where("$alias.affiliatable_type", CorporationInfo::class)
            ->orOn('character_affiliations.alliance_id', '=', "$alias.affiliatable_id")->where("$alias.affiliatable_type", AllianceInfo::class);
    }

    /**
     * @return Builder
     */
    protected function getAffiliation(): Builder
    {
        if (! isset($this->affiliation)) {
            $this->createAffiliation();
        }

        return clone $this->affiliation;
    }

    protected function createAffiliation(): void
    {
        $this->affiliation = Affiliation::query()
            ->whereRelation('role.permissions', 'name', $this->affiliationsDto->permission)
            ->whereRelation('role.members', 'user_id', $this->affiliationsDto->user->getAuthIdentifier());
    }
}