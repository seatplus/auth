<?php

declare(strict_types=1);

namespace Seatplus\Auth\Services\Roles;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Seatplus\Auth\Enums\AffiliationType;
use Seatplus\Auth\Models\Permissions\Affiliation;
use Seatplus\Eveapi\Models\Alliance\AllianceInfo;
use Seatplus\Eveapi\Models\Character\CharacterAffiliation;
use Seatplus\Eveapi\Models\Character\CharacterInfo;
use Seatplus\Eveapi\Models\Corporation\CorporationInfo;

/**
 * Resolves a role's affiliated entity ids — `(allowed ∪ inverse) ∖ forbidden` with transitive
 * corp/alliance member inclusion — as set-based SQL, never materialising whole tables into PHP.
 *
 * It exposes the same result through two façades over one engine: composable single-column
 * subqueries per id-space (for query-scoping consumers) and a bounded predicate that tests only
 * a handful of requested ids (for authorisation checks). The "everyone except X" inverse case is
 * expressed as a `NOT EXISTS` anti-join against the small inverted seed, so it never enumerates
 * the universe in memory.
 *
 * Parity with the previous relation-based expansion is deliberate and pinned by
 * tests/Feature/Services/RoleAffiliatedIdsServiceTest.php + AffiliationResolverTest.php:
 *  - corp → members and alliance → members go through character_affiliations, INNER-joined to
 *    character_infos (matching CorporationInfo::characters()/AllianceInfo::characters() HasManyThrough);
 *  - alliance → corporations uses corporation_infos.alliance_id (matching AllianceInfo::corporations() HasMany);
 *  - forbidden always wins; the inverse complement is only emitted when the role has an inverse affiliation.
 */
class AffiliationResolver
{
    private string $affiliations;

    private string $characterInfos;

    private string $corporationInfos;

    private string $allianceInfos;

    private string $characterAffiliations;

    public function __construct()
    {
        $this->affiliations = (new Affiliation)->getTable();
        $this->characterInfos = (new CharacterInfo)->getTable();
        $this->corporationInfos = (new CorporationInfo)->getTable();
        $this->allianceInfos = (new AllianceInfo)->getTable();
        $this->characterAffiliations = (new CharacterAffiliation)->getTable();
    }

    /**
     * The full resolved set across all three id-spaces (the conflated shape callers expect).
     *
     * @param  array<int, int>  $roleIds
     * @return array<int, int>
     */
    public function resolve(array $roleIds): array
    {
        return array_map('intval', $this->allSpaces($roleIds)->pluck('affiliated_id')->all());
    }

    /**
     * Of the requested ids, those covered by the role's affiliations. Binds only the requested
     * handful, so the cost scales with the request, not the database.
     *
     * @param  array<int, int>  $roleIds
     * @param  array<int, int>  $requestedIds
     * @return array<int, int>
     */
    public function coveredIds(array $roleIds, array $requestedIds): array
    {
        if ($requestedIds === []) {
            return [];
        }

        return array_map('intval', DB::query()
            ->fromSub($this->allSpaces($roleIds), 'affiliated')
            ->whereIn('affiliated.affiliated_id', $requestedIds)
            ->pluck('affiliated.affiliated_id')
            ->all());
    }

    /**
     * @param  array<int, int>  $roleIds
     */
    public function characterIdsSubquery(array $roleIds): Builder
    {
        $positive = $this->expandedCharacterIds($roleIds, AffiliationType::ALLOWED);

        if ($this->hasInverse($roleIds)) {
            $complement = DB::table($this->characterInfos)
                ->select("{$this->characterInfos}.character_id as affiliated_id")
                ->whereNotExists($this->correlatedExclusion(
                    $this->expandedCharacterIds($roleIds, AffiliationType::INVERSE),
                    "{$this->characterInfos}.character_id",
                ));

            $positive->union($complement);
        }

        return $this->exceptForbidden($positive, $this->expandedCharacterIds($roleIds, AffiliationType::FORBIDDEN));
    }

    /**
     * @param  array<int, int>  $roleIds
     */
    public function corporationIdsSubquery(array $roleIds): Builder
    {
        $positive = $this->expandedCorporationIds($roleIds, AffiliationType::ALLOWED);

        if ($this->hasInverse($roleIds)) {
            $complement = DB::table($this->corporationInfos)
                ->select("{$this->corporationInfos}.corporation_id as affiliated_id")
                ->whereNotExists($this->correlatedExclusion(
                    $this->expandedCorporationIds($roleIds, AffiliationType::INVERSE),
                    "{$this->corporationInfos}.corporation_id",
                ));

            $positive->union($complement);
        }

        return $this->exceptForbidden($positive, $this->expandedCorporationIds($roleIds, AffiliationType::FORBIDDEN));
    }

    /**
     * @param  array<int, int>  $roleIds
     */
    public function allianceIdsSubquery(array $roleIds): Builder
    {
        $positive = $this->expandedAllianceIds($roleIds, AffiliationType::ALLOWED);

        if ($this->hasInverse($roleIds)) {
            $complement = DB::table($this->allianceInfos)
                ->select("{$this->allianceInfos}.alliance_id as affiliated_id")
                ->whereNotExists($this->correlatedExclusion(
                    $this->expandedAllianceIds($roleIds, AffiliationType::INVERSE),
                    "{$this->allianceInfos}.alliance_id",
                ));

            $positive->union($complement);
        }

        return $this->exceptForbidden($positive, $this->expandedAllianceIds($roleIds, AffiliationType::FORBIDDEN));
    }

    /**
     * @param  array<int, int>  $roleIds
     */
    private function allSpaces(array $roleIds): Builder
    {
        $union = $this->characterIdsSubquery($roleIds);
        $union->union($this->corporationIdsSubquery($roleIds));
        $union->union($this->allianceIdsSubquery($roleIds));

        return $union;
    }

    /**
     * Affiliated character ids: direct character affiliations, plus members of affiliated
     * corporations and alliances (through character_affiliations, INNER-joined to character_infos).
     *
     * @param  array<int, int>  $roleIds
     */
    private function expandedCharacterIds(array $roleIds, AffiliationType $type): Builder
    {
        $direct = $this->directAffiliations($roleIds, $type, CharacterInfo::class);

        $corporationMembers = DB::table("{$this->affiliations} as a")
            ->join("{$this->characterAffiliations} as ca", 'ca.corporation_id', '=', 'a.affiliatable_id')
            ->join("{$this->characterInfos} as ci", 'ci.character_id', '=', 'ca.character_id')
            ->whereIn('a.role_id', $roleIds)
            ->whereRaw('a.type::text = ?', [$type->value])
            ->where('a.affiliatable_type', CorporationInfo::class)
            ->select('ca.character_id as affiliated_id');

        $allianceMembers = DB::table("{$this->affiliations} as a")
            ->join("{$this->characterAffiliations} as ca", 'ca.alliance_id', '=', 'a.affiliatable_id')
            ->join("{$this->characterInfos} as ci", 'ci.character_id', '=', 'ca.character_id')
            ->whereIn('a.role_id', $roleIds)
            ->whereRaw('a.type::text = ?', [$type->value])
            ->where('a.affiliatable_type', AllianceInfo::class)
            ->select('ca.character_id as affiliated_id');

        $direct->union($corporationMembers);
        $direct->union($allianceMembers);

        return $direct;
    }

    /**
     * Affiliated corporation ids: direct corporation affiliations, plus corporations belonging to
     * an affiliated alliance (corporation_infos.alliance_id — matching AllianceInfo::corporations()).
     *
     * @param  array<int, int>  $roleIds
     */
    private function expandedCorporationIds(array $roleIds, AffiliationType $type): Builder
    {
        $direct = $this->directAffiliations($roleIds, $type, CorporationInfo::class);

        $allianceCorporations = DB::table("{$this->affiliations} as a")
            ->join("{$this->corporationInfos} as ci", 'ci.alliance_id', '=', 'a.affiliatable_id')
            ->whereIn('a.role_id', $roleIds)
            ->whereRaw('a.type::text = ?', [$type->value])
            ->where('a.affiliatable_type', AllianceInfo::class)
            ->select('ci.corporation_id as affiliated_id');

        $direct->union($allianceCorporations);

        return $direct;
    }

    /**
     * Affiliated alliance ids: direct alliance affiliations only.
     *
     * @param  array<int, int>  $roleIds
     */
    private function expandedAllianceIds(array $roleIds, AffiliationType $type): Builder
    {
        return $this->directAffiliations($roleIds, $type, AllianceInfo::class);
    }

    /**
     * @param  array<int, int>  $roleIds
     */
    private function directAffiliations(array $roleIds, AffiliationType $type, string $affiliatableType): Builder
    {
        return DB::table($this->affiliations)
            ->whereIn('role_id', $roleIds)
            ->whereRaw('type::text = ?', [$type->value])
            ->where('affiliatable_type', $affiliatableType)
            ->select('affiliatable_id as affiliated_id');
    }

    /**
     * @param  array<int, int>  $roleIds
     */
    private function hasInverse(array $roleIds): bool
    {
        return DB::table($this->affiliations)
            ->whereIn('role_id', $roleIds)
            ->whereRaw('type::text = ?', [AffiliationType::INVERSE->value])
            ->exists();
    }

    /**
     * Wraps the positive set and removes the forbidden set via a NOT EXISTS anti-join
     * (never NOT IN, which a NULL in the subquery would collapse to the empty set).
     */
    private function exceptForbidden(Builder $positive, Builder $forbidden): Builder
    {
        return DB::query()
            ->fromSub($positive, 'p')
            ->select('p.affiliated_id')
            ->whereNotExists($this->correlatedExclusion($forbidden, 'p.affiliated_id'));
    }

    /**
     * A correlated `NOT EXISTS (... WHERE excluded.affiliated_id = <column>)` builder.
     */
    private function correlatedExclusion(Builder $excluded, string $column): \Closure
    {
        return fn (Builder $query) => $query
            ->fromSub($excluded, 'excluded')
            ->whereColumn('excluded.affiliated_id', $column);
    }
}
