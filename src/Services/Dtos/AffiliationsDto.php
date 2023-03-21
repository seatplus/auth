<?php

namespace Seatplus\Auth\Services\Dtos;

use Seatplus\Auth\Models\User;
class AffiliationsDto
{
    public function __construct(
        public array $permissions,
        public User $user,
        public ?array $corporation_roles = null,
    )
    {
    }


}
