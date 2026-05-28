<?php

declare(strict_types=1);

namespace Seatplus\Auth\Models\AccessControl;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Seatplus\Auth\Models\Permissions\Role;

class RoleMembership extends Model
{
    protected $table = 'role_memberships';

    public $incrementing = false;

    protected $casts = [
        'role_id' => 'integer',
        'entity_id' => 'integer',
        'can_moderate' => 'boolean',
    ];

    protected $fillable = [
        'role_id',
        'entity_type',
        'entity_id',
        'can_moderate',
        'status',
    ];

    /** @return BelongsTo<Role, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id');
    }

    /** @return MorphTo<Model, $this> */
    public function entity(): MorphTo
    {
        return $this->morphTo();
    }
}
