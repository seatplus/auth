<?php

declare(strict_types=1);

namespace Seatplus\Auth\Services\Roles;

use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
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
 * Two façades over one engine:
 *  - {@see coveredIds()} — a bounded predicate that tests ONLY the requested ids. The requested set is
 *    pushed into the inverse-complement's entity read, so the "everyone except X" universe is never
 *    enumerated regardless of the query planner.
 *  - {@see characterIdsSubquery()} / {@see corporationIdsSubquery()} / {@see allianceIdsSubquery()} —
 *    composable single-column subqueries per id-space (for query-scoping consumers).
 *
 * Leaf reads use `Model::query()`; the models are scope-free, so this is row-for-row equivalent to a raw
 * query. The set-operation wrappers (union / forbidden anti-join) are query-builder derived tables, so the
 * composed façades return {@see Builder}.
 *
 * Parity with the previous relation-based expansion is pinned by
 * tests/Feature/Services/RoleAffiliatedIdsServiceTest.php + AffiliationResolverTest.php:
 *  - corp/alliance → members go through character_affiliations, INNER-joined to character_infos
 *    (matching CorporationInfo::characters()/AllianceInfo::characters() HasManyThrough);
 *  - alliance → corporations uses corporation_infos.alliance_id (matching AllianceInfo::corporations() HasMany);
 *  - forbidden always wins (NOT EXISTS anti-join); the inverse complement is emitted only when the role has
 *    an inverse affiliation.
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
     * Of the requested ids, those covered by the role's affiliations. The requested set bounds every arm —
     * including the inverse complement's entity read — so the universe is never enumerated.
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

        $union = $this->characterSpace($roleIds, $requestedIds);
        $union->union($this->corporationSpace($roleIds, $requestedIds));
        $union->union($this->allianceSpace($roleIds, $requestedIds));

        return array_map('intval', DB::query()
            ->fromSub($union, 'affiliated')
            ->whereIn('affiliated.affiliated_id', $requestedIds)
            ->pluck('affiliated.affiliated_id')
            ->all());
    }

    /**
     * @param  array<int, int>  $roleIds
     */
    public function characterIdsSubquery(array $roleIds): Builder
    {
        return $this->characterSpace($roleIds, null);
    }

    /**
     * @param  array<int, int>  $roleIds
     */
    public function corporationIdsSubquery(array $roleIds): Builder
    {
        return $this->corporationSpace($roleIds, null);
    }

    /**
     * @param  array<int, int>  $roleIds
     */
    public function allianceIdsSubquery(array $roleIds): Builder
    {
        return $this->allianceSpace($roleIds, null);
    }

    /**
     * @param  array<int, int>  $roleIds
     */
    private function allSpaces(array $roleIds): Builder
    {
        $union = $this->characterSpace($roleIds, null);
        $union->union($this->corporationSpace($roleIds, null));
        $union->union($this->allianceSpace($roleIds, null));

        return $union;
    }

    /**
     * @param  array<int, int>  $roleIds
     * @param  array<int, int>|null  $restrictTo  bound the inverse complement to these ids (null = full enumeration)
     */
    private function characterSpace(array $roleIds, ?array $restrictTo): Builder
    {
        $positive = $this->expandedCharacterIds($roleIds, AffiliationType::ALLOWED);

        if ($this->hasInverse($roleIds)) {
            $complement = CharacterInfo::query()
                ->select("{$this->characterInfos}.character_id as affiliated_id")
                ->whereNotExists($this->correlatedExclusion(
                    $this->expandedCharacterIds($roleIds, AffiliationType::INVERSE),
                    "{$this->characterInfos}.character_id",
                ));

            if ($restrictTo !== null) {
                $complement->whereIn("{$this->characterInfos}.character_id", $restrictTo);
            }

            $positive->union($complement);
        }

        return $this->exceptForbidden($positive, $this->expandedCharacterIds($roleIds, AffiliationType::FORBIDDEN));
    }

    /**
     * @param  array<int, int>  $roleIds
     * @param  array<int, int>|null  $restrictTo
     */
    private function corporationSpace(array $roleIds, ?array $restrictTo): Builder
    {
        $positive = $this->expandedCorporationIds($roleIds, AffiliationType::ALLOWED);

        if ($this->hasInverse($roleIds)) {
            $complement = CorporationInfo::query()
                ->select("{$this->corporationInfos}.corporation_id as affiliated_id")
                ->whereNotExists($this->correlatedExclusion(
                    $this->expandedCorporationIds($roleIds, AffiliationType::INVERSE),
                    "{$this->corporationInfos}.corporation_id",
                ));

            if ($restrictTo !== null) {
                $complement->whereIn("{$this->corporationInfos}.corporation_id", $restrictTo);
            }

            $positive->union($complement);
        }

        return $this->exceptForbidden($positive, $this->expandedCorporationIds($roleIds, AffiliationType::FORBIDDEN));
    }

    /**
     * @param  array<int, int>  $roleIds
     * @param  array<int, int>|null  $restrictTo
     */
    private function allianceSpace(array $roleIds, ?array $restrictTo): Builder
    {
        $positive = $this->expandedAllianceIds($roleIds, AffiliationType::ALLOWED);

        if ($this->hasInverse($roleIds)) {
            $complement = AllianceInfo::query()
                ->select("{$this->allianceInfos}.alliance_id as affiliated_id")
                ->whereNotExists($this->correlatedExclusion(
                    $this->expandedAllianceIds($roleIds, AffiliationType::INVERSE),
                    "{$this->allianceInfos}.alliance_id",
                ));

            if ($restrictTo !== null) {
                $complement->whereIn("{$this->allianceInfos}.alliance_id", $restrictTo);
            }

            $positive->union($complement);
        }

        return $this->exceptForbidden($positive, $this->expandedAllianceIds($roleIds, AffiliationType::FORBIDDEN));
    }

    /**
     * Affiliated character ids: direct character affiliations, plus members of affiliated corporations and
     * alliances (through character_affiliations, INNER-joined to character_infos).
     *
     * @param  array<int, int>  $roleIds
     */
    private function expandedCharacterIds(array $roleIds, AffiliationType $type): EloquentBuilder
    {
        $direct = $this->directAffiliations($roleIds, $type, CharacterInfo::class);

        $corporationMembers = Affiliation::query()
            ->from("{$this->affiliations} as a")
            ->join("{$this->characterAffiliations} as ca", 'ca.corporation_id', '=', 'a.affiliatable_id')
            ->join("{$this->characterInfos} as ci", 'ci.character_id', '=', 'ca.character_id')
            ->whereIn('a.role_id', $roleIds)
            ->whereRaw('a.type::text = ?', [$type->value])
            ->where('a.affiliatable_type', CorporationInfo::class)
            ->select('ca.character_id as affiliated_id');

        $allianceMembers = Affiliation::query()
            ->from("{$this->affiliations} as a")
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
     * Affiliated corporation ids: direct corporation affiliations, plus corporations belonging to an
     * affiliated alliance (corporation_infos.alliance_id — matching AllianceInfo::corporations()).
     *
     * @param  array<int, int>  $roleIds
     */
    private function expandedCorporationIds(array $roleIds, AffiliationType $type): EloquentBuilder
    {
        $direct = $this->directAffiliations($roleIds, $type, CorporationInfo::class);

        $allianceCorporations = Affiliation::query()
            ->from("{$this->affiliations} as a")
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
    private function expandedAllianceIds(array $roleIds, AffiliationType $type): EloquentBuilder
    {
        return $this->directAffiliations($roleIds, $type, AllianceInfo::class);
    }

    /**
     * @param  array<int, int>  $roleIds
     */
    private function directAffiliations(array $roleIds, AffiliationType $type, string $affiliatableType): EloquentBuilder
    {
        return Affiliation::query()
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
        return Affiliation::query()
            ->whereIn('role_id', $roleIds)
            ->whereRaw('type::text = ?', [AffiliationType::INVERSE->value])
            ->exists();
    }

    /**
     * Wraps the positive set and removes the forbidden set via a NOT EXISTS anti-join
     * (never NOT IN, which a NULL in the subquery would collapse to the empty set).
     */
    private function exceptForbidden(EloquentBuilder $positive, EloquentBuilder $forbidden): Builder
    {
        return DB::query()
            ->fromSub($positive, 'p')
            ->select('p.affiliated_id')
            ->whereNotExists($this->correlatedExclusion($forbidden, 'p.affiliated_id'));
    }

    /**
     * A correlated `NOT EXISTS (... WHERE excluded.affiliated_id = <column>)` builder.
     */
    private function correlatedExclusion(EloquentBuilder $excluded, string $column): \Closure
    {
        return fn (Builder $query) => $query
            ->fromSub($excluded, 'excluded')
            ->whereColumn('excluded.affiliated_id', $column);
    }
}
