<?php

namespace Seatplus\Auth\Services\Affiliations;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Seatplus\Auth\Models\CharacterUser;
use Seatplus\Auth\Services\Dtos\AffiliationsDto;
use Seatplus\Eveapi\Models\Character\CharacterAffiliation;

class GetOwnedAffiliatedIdsService
{
    public function __construct(
        private AffiliationsDto $affiliationsDto
    ) {
    }

    public static function make(AffiliationsDto $affiliationsDto)
    {
        return new static($affiliationsDto);
    }

    public function getQuery() : Builder
    {

        $character_query = $this->getCharacterQuery();

        if(!$this->affiliationsDto->corporation_roles) {

            return $character_query;
        }

        $corporation_query = $this->getCorporationQuery();

        return $character_query
            ->union($corporation_query);

    }

    private function getCharacterQuery() : Builder
    {
        return CharacterAffiliation::query()
            ->join(
                'character_users',
                fn (JoinClause $join) => $join
                    ->on('character_affiliations.character_id', '=', 'character_users.character_id')
                    ->where('user_id', $this->affiliationsDto->user->getAuthIdentifier())
            )
            ->select('character_affiliations.character_id as affiliated_id');
    }

    private function getCorporationQuery() : Builder
    {
        $character_users = CharacterUser::query()
            ->whereHas(
                'character.roles',
                function (Builder $query) {
                    $query->whereJsonContains('roles', 'Director');

                    foreach ($this->affiliationsDto->corporation_roles as $role) {
                        $query->orWhereJsonContains('roles', $role);
                    }
                }
            )
            ->where('user_id', $this->affiliationsDto->user->getAuthIdentifier());

        return CharacterAffiliation::query()
            ->joinSub(
                $character_users,
                'character_users_sub',
                'character_affiliations.character_id',
                '=',
                'character_users_sub.character_id'
            )
            ->select('character_affiliations.corporation_id as affiliated_id');
    }
}
