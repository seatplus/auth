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

abstract class GetAffiliatedIdsServiceBase
{
    private Builder $affiliations;

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
    protected function getAffiliations(): Builder
    {
        if (! isset($this->affiliations)) {
            $this->createAffiliations();
        }

        return clone $this->affiliations;
    }

    protected function createAffiliations(): void
    {

        $permissions = $this->affiliationsDto->permissions;

        $affiliations = Affiliation::query()
            ->whereRelation('role.permissions', 'name', array_shift($permissions))
            ->whereRelation('role.members', 'user_id', $this->affiliationsDto->user->getAuthIdentifier());

        foreach ($permissions as $permission) {
            $affiliations->whereRelation('role.permissions', 'name', $permission);
        }

        $this->affiliations = $affiliations;
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
                        $forbidden,
                        'remove_forbidden',
                        'remove_forbidden.forbidden_id',
                        '=',
                        'affiliations.affiliated_id'
                    )
                    ->whereNull('forbidden_id')
            )
            ->select('affiliated_id')
            ;
    }
}
