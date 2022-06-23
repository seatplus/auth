<?php

namespace Seatplus\Auth\Services\Dtos;

use Seatplus\Auth\Models\User;
use Spatie\DataTransferObject\DataTransferObject;

#[Strict]
class AffiliationsDto extends DataTransferObject
{
    public array $permissions;
    public User $user;
    public ?array $corporation_roles;
}
