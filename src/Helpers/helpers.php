<?php

/*
 * MIT License
 *
 * Copyright (c) 2019, 2020, 2021 Felix Huber
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

use Seatplus\Auth\Actions\GetAffiliatedIdsByPermissionArray;

/**
 * @deprecated deprecated since version 2.0
 */
if (! function_exists('getAffiliatedIdsByClass')) {
    /**
     * A helper to get all affiliated Characters.
     *
     * @param  string  $class
     * @param  string  $role
     * @return array
     * @deprecated deprecated since version 2.0
     */
    function getAffiliatedIdsByClass(string $class, string $role = ''): array
    {
        $permission_name = config('eveapi.permissions.' . $class);

        return getAffiliatedIdsByPermission($permission_name, $role);
    }
}

/**
 * @deprecated deprecated since version 2.0
 */
if (! function_exists('getAffiliatedIdsByPermission')) {
    /**
     * A helper to get all affiliated Characters.
     *
     * @param  string  $class
     * @param  string  $role
     * @return array
     * @deprecated deprecated since version 2.0
     */
    function getAffiliatedIdsByPermission(string $permission, string $role = ''): array
    {
        try {
            $ids = (new GetAffiliatedIdsByPermissionArray($permission, $role))->execute();
        } catch (Exception $exception) {
            report($exception);
            $ids = [];
        }

        return $ids;
    }
}
