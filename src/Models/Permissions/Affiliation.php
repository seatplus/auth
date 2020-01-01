<?php

namespace Seatplus\Auth\Models\Permissions;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Seatplus\Eveapi\Models\Character\CharacterAffiliation;

class Affiliation extends Model
{
    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var bool
     */
    protected static $unguarded = true;

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'role_id' => 'integer'
    ];

    public function role()
    {
        return $this->belongsTo(Role::class, 'id', 'role_id');
    }

    public function scopeAllowedAffiliatedCharacterIds(Builder $query)
    {
        return $query
            ->addSelect(['affiliated_character_ids_through_allowed' => CharacterAffiliation::select('character_id')
                ->orWhereColumn([
                    ['character_id', 'affiliations.character_id'],
                    ['affiliations.type','allowed']
                ])
                ->orWhereColumn([
                    ['corporation_id', 'affiliations.corporation_id'],
                    ['affiliations.type','allowed']
                ])
                ->orWhereColumn([
                    ['alliance_id', 'affiliations.alliance_id'],
                    ['affiliations.type','allowed']
                ])
            ]);
    }

    public function scopeAffiliatedCharacterIdsThroughInverse(Builder $query)
    {
        return $query
            ->addSelect(['affiliated_character_ids_through_inverse' => CharacterAffiliation::select('character_id')
                ->orWhereColumn([
                    ['character_id', '<>', 'affiliations.character_id'],
                    ['affiliations.type','inverse']
                ])
                ->orWhereColumn([
                    ['corporation_id', '<>', 'affiliations.corporation_id'],
                    ['affiliations.type','inverse']
                ])
                ->orWhereColumn([
                    ['alliance_id', '<>', 'affiliations.alliance_id'],
                    ['affiliations.type','inverse']
                ])
            ]);
    }

    public function scopeInvertedCharacterIdsThroughInverse(Builder $query)
    {
        return $query
            ->addSelect(['inverted_character_ids_through_inverse' => CharacterAffiliation::select('character_id')
                ->orWhereColumn([
                    ['character_id', 'affiliations.character_id'],
                    ['affiliations.type','inverse']
                ])
                ->orWhereColumn([
                    ['corporation_id', 'affiliations.corporation_id'],
                    ['affiliations.type','inverse']
                ])
                ->orWhereColumn([
                    ['alliance_id', 'affiliations.alliance_id'],
                    ['affiliations.type','inverse']
                ])
            ]);
    }

    public function scopeForbiddenAffiliatedCharacterIds(Builder $query)
    {
        return $query
            ->addSelect(['forbidden_character_ids' => CharacterAffiliation::select('character_id')
                ->orWhereColumn([
                    ['character_id', 'affiliations.character_id'],
                    ['affiliations.type','forbidden']
                ])
                ->orWhereColumn([
                    ['corporation_id', 'affiliations.corporation_id'],
                    ['affiliations.type','forbidden']
                ])
                ->orWhereColumn([
                    ['alliance_id', 'affiliations.alliance_id'],
                    ['affiliations.type','forbidden']
                ])
            ]);
    }


}
