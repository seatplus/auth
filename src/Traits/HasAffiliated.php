<?php

namespace Seatplus\Auth\Traits;



use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Facades\DB;
use Seatplus\Auth\Enums\AffiliationType;
use Seatplus\Auth\Models\AccessControl\AclAffiliation;
use Seatplus\Auth\Models\Permissions\Affiliation;
use Seatplus\Auth\Models\Permissions\Permission;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Models\User;
use Seatplus\Eveapi\Models\Alliance\AllianceInfo;
use Seatplus\Eveapi\Models\Character\CharacterAffiliation;
use Seatplus\Eveapi\Models\Character\CharacterInfo;
use Seatplus\Eveapi\Models\Corporation\CorporationInfo;
use function Pest\Laravel\get;

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

        $forbidden = $this->getForbiddenAffiliatedCharacterAffiliations()
           /* ->leftJoinSub(
                $this->getOwnedCharacterAffiliations(),
                'owned_affiliations',
                function (JoinClause $join) {
                    $join->on('')
                }
            )*/
        ;

        return $query->joinSub($character_affiliations, 'character_affiliations', fn (JoinClause $join) => $join
            ->on($this->getTable() . ".$column", '=', 'character_affiliations.character_id')
        )
            //->get()->dd()
            ->whereNotIn('character_id', fn(\Illuminate\Database\Query\Builder $query) => $query
                ->fromSub($forbidden, 'helper')
                ->select('helper.character_id')
            )
            ->select($this->getTable() . ".*")
            ;

        //->get()->dd('test')

        /*return $query->when(!auth()->guest(), fn (Builder $query) => $query
            ->join('character_users', fn (JoinClause $join) => $join
                ->on($this->getTable() . ".$column", '=', 'character_users.character_id')
                ->where('user_id', auth()->user()->getAuthIdentifier())
            )
        );*/
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
        $this->createAffiliation();

        $allowed =  $this->getAllowedAffiliatedCharacterAffiliations();
        $inverted = $this->getInvertedAffiliatedCharacterAffiliations();
        $forbidden = $this->getForbiddenAffiliatedCharacterAffiliations()->select('character_id');

        /*return $forbidden
            ->rightJoinSub(
                $allowed->union($inverted),
                'not_forbidden_entities',
                fn(JoinClause $join) => $join->on('not_forbidden_entities.character_id', '=', 'character_affiliations.character_id') // r.value = l.value
            )
            ->when($forbidden->count(), fn($query) => $query->whereNull('type') )
            ->select('not_forbidden_entities.*');*/

        $combined =  $allowed
            ->union($inverted)
        ;

        return $combined
            //->get()->dd()
            ->whereNotIn('character_id', fn(\Illuminate\Database\Query\Builder $query) => $query
                ->fromSub($forbidden, 'helper')
                ->select('helper.character_id')
                //->get()->dd('test')
            )
            ->select('character_affiliations.*')
            //->get()->dd()
            ;

            /*->whereNotIn('character_id', function ($query) use ($forbidden) {

                $type = AffiliationType::INVERSE;
                $alias = sprintf('%s_entities', $type->value());

                $affiliation = $this->getAffiliation()->where('type', $type->value());

                $query->select('helper.character_id')
                    ->fromSub($forbidden, 'helper');
                    //->where(fn($query) => $query->where('helper.affiliatable_type', CharacterInfo::class)->whereColumn('helper.affiliatable_id', 'character_affiliations.character_id'))
                    //->orWhere(fn($query) => $query->where('helper.affiliatable_type', CharacterInfo::class)->whereColumn('helper.affiliatable_id', 'character_affiliations.corporation_id'))
                    //->orWhere(fn($query) => $query->where('helper.affiliatable_type', CharacterInfo::class)->whereColumn('helper.affiliatable_id', 'character_affiliations.alliance_id'));
            });*/

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

        /*$affiliation = $this->getOwnedCharacterAffiliations()
            //->get()->dd('forbidden')
            ->get()->dd()
            ;*/

        $affiliation = $this->getAffiliation()->where('type', $type->value())
            ->whereNotExists(fn(\Illuminate\Database\Query\Builder $query) => $query
                ->select(DB::raw(1))
                ->fromSub($this->getOwnedCharacterAffiliations(), 'owned')
                ->whereColumn('affiliations.affiliatable_id', 'owned.character_id')
                ->orWhereColumn('affiliations.affiliatable_id', 'owned.corporation_id')
                ->orWhereColumn('affiliations.affiliatable_id', 'owned.alliance_id')
            )
            /*->leftJoinSub(
                $this->getOwnedCharacterAffiliations(),
                'owned_entities',
                fn(JoinClause $join) => $this->joinAffiliatedCharacterAffiliations($join, 'owned_entities')
            )*/
            //->get()->dd()
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