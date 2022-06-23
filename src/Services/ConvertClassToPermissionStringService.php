<?php

namespace Seatplus\Auth\Services;

class ConvertClassToPermissionStringService
{
    public static function get(string $class) : string
    {
        throw_unless(class_exists($class), 'Provided class does not exist');

        return config('eveapi.permissions.' . $class) ?? $class;
    }
}
