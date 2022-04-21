<?php

namespace Seatplus\Auth\Enums;

enum AffiliationType
{
    case ALLOWED;
    case INVERSE;
    case FORBIDDEN;

    public function operator() : string
    {
        return match ($this)
        {
            self::ALLOWED, self::FORBIDDEN => '=',
            self::INVERSE => '='
        };
    }

    public function value() : string
    {
        return match ($this)
        {
            self::ALLOWED => 'allowed',
            self::FORBIDDEN => 'forbidden',
            self::INVERSE => 'inverse'
        };
    }
}