<?php

declare(strict_types=1);

namespace Seatplus\Auth\Models\AccessControl;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Seatplus\Auth\Models\Permissions\Role;

#[Fillable([
    'role_id',
    'entity_type',
    'entity_id',
    'can_moderate',
    'status',
])]
#[WithoutIncrementing]
class RoleMembership extends Model
{
    #[\Override]
    protected $table = 'role_memberships';

    #[\Override]
    protected function casts(): array
    {
        return [
            'role_id' => 'integer',
            'entity_id' => 'integer',
            'can_moderate' => 'boolean',
        ];
    }

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
