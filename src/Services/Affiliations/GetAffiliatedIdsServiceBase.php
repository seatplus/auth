<?php

namespace Seatplus\Auth\Services\Affiliations;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Seatplus\Auth\Models\Permissions\Affiliation;
use Seatplus\Auth\Services\Dtos\AffiliationsDto;
use Seatplus\Eveapi\Models\Alliance\AllianceInfo;
use Seatplus\Eveapi\Models\Character\CharacterInfo;
use Seatplus\Eveapi\Models\Corporation\CorporationInfo;
use function Pest\Laravel\get;

abstract class GetAffiliatedIdsServiceBase
{
    private Builder $affiliation;

    public function __construct(
        protected AffiliationsDto $affiliationsDto
    ) {
    }

    protected function joinAffiliatedCharacterAffiliations(JoinClause $join, string $alias) : JoinClause
    {
        return $join
            ->on('character_affiliations.character_id', '=', "$alias.affiliatable_id")->where("$alias.affiliatable_type", CharacterInfo::class)
            ->orOn('character_affiliations.corporation_id', '=', "$alias.affiliatable_id")->where("$alias.affiliatable_type", CorporationInfo::class)
            ->orOn('character_affiliations.alliance_id', '=', "$alias.affiliatable_id")->where("$alias.affiliatable_type", AllianceInfo::class);
    }

    protected function joinAffiliatedCorporationAffiliations(JoinClause $join, string $alias) : JoinClause
    {
        return $join
            ->on('character_affiliations.corporation_id', '=', "$alias.affiliatable_id")->where("$alias.affiliatable_type", CorporationInfo::class)
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

    protected function removeForbiddenAffiliations(Builder $query) : QueryBuilder
    {
        $forbidden = GetForbiddenAffiliatedIdService::make($this->affiliationsDto)->getQuery();

        return \DB::query()
            ->fromSub($query, 'affiliations')
            ->when(
                $forbidden->count(),
                fn (QueryBuilder $query) => $query
                    ->leftJoinSub(
                        $forbidden, 'remove_forbidden', 'remove_forbidden.forbidden_id', '=', 'affiliations.affiliated_id'
                    )
                    ->whereNull('forbidden_id')
            )
            ->select('affiliated_id')
            ;
    }
}
