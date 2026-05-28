<?php

declare(strict_types=1);

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
use Seatplus\Auth\Models\User;
use Seatplus\Auth\Services\Permissions\CanUserService;
use Seatplus\Auth\Services\Permissions\DTO\ValidateIdsDTO;

class CheckAuthorization
{
    public function __construct(
        private ?CanUserService $canUserService = null
    ) {
        $this->canUserService ??= new CanUserService;
    }

    public function handle(Request $request, Closure $next, string $permissions, ?string $corporation_role = null): mixed
    {
        /** @var User $user */
        $user = auth()->user();
        $ids_dto = ValidateIdsDTO::fromRequest($request);
        $permissions = explode('|', $permissions);
        $corporation_role = explode('|', (string) $corporation_role);

        abort_unless($this->canUserService->check(
            user: $user,
            idsDTO: $ids_dto,
            permissions: $permissions,
            corporation_roles: $corporation_role
        ), 403);

        return $next($request);
    }
}
