<?php

namespace Seatplus\Auth\Services;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Seatplus\Auth\Models\CharacterUser;
use Seatplus\Auth\Services\Dtos\AffiliationsDto;
use Seatplus\Eveapi\Models\Character\CharacterAffiliation;

class GetOwnedCharacterAffiliationsService
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
        return CharacterAffiliation::query()
            ->when(
                $this->affiliationsDto->corporation_roles,
                // corporation scope
                fn (Builder $query) => $this->getOwnedCharacterAffiliationsByCorporationScope($query),
                // character scope
                fn (Builder $query) => $this->getOwnedCharacterAffiliationsByCharacterScope($query)
            )
            ->select('character_affiliations.*');
    }

    private function getOwnedCharacterAffiliationsByCorporationScope(Builder $query) : Builder
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

        return $query->joinSub(
            $character_users,
            'character_users_sub',
            'character_affiliations.character_id',
            '=',
            'character_users_sub.character_id'
        );
    }

    private function getOwnedCharacterAffiliationsByCharacterScope(Builder $query) : Builder
    {
        return $query->join(
            'character_users',
            fn (JoinClause $join) => $join
                ->on('character_affiliations.character_id', '=', 'character_users.character_id')
                ->where('user_id', $this->affiliationsDto->user->getAuthIdentifier())
        );
    }
}
