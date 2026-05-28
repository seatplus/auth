<?php

declare(strict_types=1);

namespace Seatplus\Auth\Exceptions;

use RuntimeException;

final class CriteriaNotMetException extends RuntimeException
{
    public function __construct(string $message = 'User does not meet criteria to join role')
    {
        parent::__construct($message);
    }
}
