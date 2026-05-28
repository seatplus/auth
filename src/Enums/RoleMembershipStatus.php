<?php

declare(strict_types=1);

namespace Seatplus\Auth\Enums;

enum RoleMembershipStatus: string
{
    case ACTIVE = 'active';
    case INACTIVE = 'inactive';
    case PENDING = 'pending';
}
