<?php


namespace Seatplus\Auth\Models\AccessControl;


use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Seatplus\Auth\Models\Permissions\Role;

class AclAffiliation extends Model
{
    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var array
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'can_moderate' => 'boolean',
    ];

    public function affiliatable()
    {
        return $this->morphTo();
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'id', 'role_id');
    }

    public function getCharacterIdsAttribute(): Collection
    {
        return $this->affiliatable ? $this->affiliatable->characters->pluck('character_id') : collect();
    }

}
