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

namespace Seatplus\Auth\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Seatplus\Auth\Models\CharacterUser;
use Seatplus\Auth\Models\User;

class CheckPermissionOrCorporationRole
{
    /**
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $permissions, ?string $corporation_role = null)
    {
        if (! $request->user()) {
            return abort(401);
        }

        // validate request and set requsted ids
        // we do this before fast tracking superuser to ensure superuser requests are valid too.

        $this->checkPermission($permissions, $corporation_role);

        return $next($request);
    }

    private function checkPermission(string $permissions, ?string $corporation_role) : void
    {
        if ($this->getUser()->can('superuser')) {
            return;
        }

        $permissions = explode('|', $permissions);

        if ($this->getUser()->hasAnyPermission($permissions)) {
            return;
        }

        if ($this->hasCorporationRole($corporation_role)) {
            return;
        }

        abort('401', 'You are not authorized to perform this action');
    }

    private function hasCorporationRole(?string $corporation_role) : bool
    {
        if (is_null($corporation_role)) {
            return false;
        }

        return CharacterUser::query()
            ->whereHas(
                'character.roles',
                fn ($query) => $query
                    ->whereJsonContains('roles', 'Director')
                    ->orWhereJsonContains('roles', $corporation_role)
            )
            ->where('user_id', $this->getUser()->getAuthIdentifier())
            ->exists();
    }

    public function getUser(): User
    {
        return User::find(auth()->user()->getAuthIdentifier());
    }
}
