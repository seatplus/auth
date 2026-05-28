<?php

declare(strict_types=1);

namespace Seatplus\Auth\Enums;

enum AffiliationType: string
{
    case ALLOWED = 'allowed';
    case INVERSE = 'inverse';
    case FORBIDDEN = 'forbidden';
}
