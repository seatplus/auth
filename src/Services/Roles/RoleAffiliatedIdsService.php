<?php

declare(strict_types=1);

namespace Seatplus\Auth\Services\Roles;

use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Seatplus\Auth\Enums\AffiliationType;
use Seatplus\Auth\Models\Permissions\Affiliation;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Eveapi\Models\Alliance\AllianceInfo;
use Seatplus\Eveapi\Models\Character\CharacterInfo;
use Seatplus\Eveapi\Models\Corporation\CorporationInfo;

class RoleAffiliatedIdsService
{
    public static function get(Role $role): array
    {

        return (new self)->buildAffiliatedIds($role);
    }

    private function buildInverse(Collection $inverted): Collection
    {

        return CharacterInfo::query()->whereNotIn('character_id', $inverted)->pluck('character_id')
            ->merge(CorporationInfo::query()->whereNotIn('corporation_id', $inverted)->pluck('corporation_id'))
            ->merge(AllianceInfo::query()->whereNotIn('alliance_id', $inverted)->pluck('alliance_id'));
    }

    private function buildAffiliatedIds(Role $role): array
    {
        $role = $this->loadMissingRelationships($role);

        $allowed = collect();
        $inverted = collect();
        $forbidden = collect();

        $role->affiliations->each(function (Affiliation $affiliation) use (&$allowed, &$inverted, &$forbidden) {

            $affiliated_ids = $affiliation->affiliated_ids;

            match ($affiliation->type) {
                AffiliationType::ALLOWED->value => $allowed = $allowed->merge($affiliated_ids),
                AffiliationType::INVERSE->value => $inverted = $inverted->merge($affiliated_ids),
                AffiliationType::FORBIDDEN->value => $forbidden = $forbidden->merge($affiliated_ids),
            };
        });

        // if ids are present,
        // build inverse of inverted and merge with allowed
        if ($inverted->isNotEmpty()) {
            $allowed = $allowed->merge($this->buildInverse($inverted));
        }

        // remove forbidden
        $allowed = $allowed->diff($forbidden);

        return $allowed->all();
    }

    private function loadMissingRelationships(Role $role): Role
    {
        return $role->loadMissing([
            'affiliations.affiliatable' => fn (MorphTo $morph_to) => $morph_to
                ->morphWith([
                    CorporationInfo::class => 'characters',
                    AllianceInfo::class => ['characters', 'corporations'],
                ]),
        ]);
    }
}
