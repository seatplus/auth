<?php

declare(strict_types=1);

namespace Seatplus\Auth\Enums;

enum RoleType: string
{
    case AUTOMATIC = 'automatic';
    case ON_REQUEST = 'on-request';
    case MANUAL = 'manual';
    case OPT_IN = 'opt-in';
}
