<?php

namespace Seatplus\Auth\Traits;



use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Seatplus\Auth\Enums\AffiliationType;
use Seatplus\Auth\Models\Permissions\Affiliation;
use Seatplus\Auth\Models\User;
use Seatplus\Eveapi\Models\Alliance\AllianceInfo;
use Seatplus\Eveapi\Models\Character\CharacterAffiliation;
use Seatplus\Eveapi\Models\Character\CharacterInfo;
use Seatplus\Eveapi\Models\Corporation\CorporationInfo;

trait HasAffiliated
{

    private User $user;
    private Builder $affiliation;
    private string $permission;

    public function scopeAffiliatedCharacters(Builder $query, string $column, null|string $permission = null)
    {
        throw_if(auth()->guest(), 'Unauthenticated');

        if($this->getUser()->can('superuser')) {
            return $query;
        }

        $this->setPermission($permission);

        $character_affiliations = $this->getOwnedCharacterAffiliations()
            ->union($this->getAffiliatedCharacterAffiliations());

        $forbidden = $this->getForbiddenAffiliatedCharacterAffiliations();

        return $query->joinSub($character_affiliations, 'character_affiliations', fn (JoinClause $join) => $join
            ->on($this->getTable() . ".$column", '=', 'character_affiliations.character_id')
        )
            ->whereNotIn('character_id', fn(QueryBuilder $query) => $query
                ->fromSub($forbidden, 'forbidden_characters')
                ->select('forbidden_characters.character_id')
            )
            ->select($this->getTable() . ".*");
    }

    private function getOwnedCharacterAffiliations() : Builder
    {
        return CharacterAffiliation::query()
            ->join('character_users', fn (JoinClause $join) => $join
                ->on('character_affiliations.character_id', '=', 'character_users.character_id')
                ->where('user_id', $this->getUser()->getAuthIdentifier())
            )
            ->select('character_affiliations.*');
    }

    private function convertToPermissionString(string $permission) : string
    {

        if(class_exists($permission)) {
            return config('eveapi.permissions.' . $permission) ?? $permission;
        }

        return $permission;
    }

    private function getAffiliatedCharacterAffiliations() : Builder
    {

        $allowed =  $this->getAllowedAffiliatedCharacterAffiliations();
        $inverted = $this->getInvertedAffiliatedCharacterAffiliations();

        return $allowed
            ->union($inverted)
            ->select('character_affiliations.*');

    }

    private function getUser(): User
    {
       if(!isset($this->user)) {
           $this->user = auth()->user();
       }

        return $this->user;
    }

    private function getAllowedAffiliatedCharacterAffiliations() : Builder
    {

        $type = AffiliationType::ALLOWED;
        $alias = sprintf('%s_entities', $type->value());

        return CharacterAffiliation::query()
            ->joinSub(
                $this->getAffiliation()->where('type', $type->value()),
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
                fn(Builder $query) => $query
                    ->leftJoinSub(
                        $affiliation,
                        $alias,
                        fn (JoinClause $join) => $this->joinAffiliatedCharacterAffiliations($join, $alias)
                    )
                    ->whereNull("$alias.affiliatable_id")
                ,
                fn (Builder $query) => $query->whereNull('character_id')
            )
            ->select('character_affiliations.*')
            ;
    }

    private function getForbiddenAffiliatedCharacterAffiliations() : Builder
    {

        $type = AffiliationType::FORBIDDEN;
        $alias = sprintf('%s_entities', $type->value());

        $affiliation = $this->getAffiliation()->where('type', $type->value())
            ->whereNotExists(fn(QueryBuilder $query) => $query
                ->select(DB::raw(1))
                ->fromSub($this->getOwnedCharacterAffiliations(), 'owned')
                ->whereColumn('affiliations.affiliatable_id', 'owned.character_id')
            )
        ;

        return CharacterAffiliation::query()
            ->when(
                $affiliation->count(),
                fn(Builder $query) => $query
                    ->joinSub(
                        $affiliation,
                        $alias,
                        fn (JoinClause $join) => $this->joinAffiliatedCharacterAffiliations($join, $alias)
                    )
                ,
                fn (Builder $query) => $query->whereNull('character_affiliations.character_id')
            )
            ->select('character_affiliations.*')
            ;
    }

    private function joinAffiliatedCharacterAffiliations(JoinClause $join, string $alias) : JoinClause
    {
        return $join
            ->on('character_affiliations.character_id', '=', "$alias.affiliatable_id")->where("$alias.affiliatable_type", CharacterInfo::class)
            ->orOn('character_affiliations.corporation_id', '=', "$alias.affiliatable_id")->where("$alias.affiliatable_type", CorporationInfo::class)
            ->orOn('character_affiliations.alliance_id', '=', "$alias.affiliatable_id")->where("$alias.affiliatable_type", AllianceInfo::class);
    }

    /**
     * @return Builder
     */
    public function getAffiliation(): Builder
    {
        if(!isset($this->affiliation)) {
            $this->createAffiliation();
        }

        return clone $this->affiliation;
    }

    public function createAffiliation(): void
    {
        $this->affiliation = Affiliation::query()
            ->whereRelation('role.permissions', 'name', $this->getPermission())
            ->whereRelation('role.members', 'user_id', $this->getUser()->getAuthIdentifier());
    }

    /**
     * @return string
     */
    public function getPermission(): string
    {
        return $this->permission;
    }

    /**
     * @param string|null $permission
     */
    public function setPermission(?string $permission): void
    {
        if(is_null($permission)) {
            $permission = get_class($this);
        }

        $permission = $this->convertToPermissionString($permission);

        $this->permission = $permission;
    }


}