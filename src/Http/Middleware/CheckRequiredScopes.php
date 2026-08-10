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
use Seatplus\Auth\Services\SsoScopes\IsUserCompliantService;
use Symfony\Component\HttpFoundation\Response;

class CheckRequiredScopes
{
    public function __construct(
        private readonly IsUserCompliantService $isUserCompliantService = new IsUserCompliantService,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return $next($request);
        }

        // Resolved once: this used to call check() and then getMissingScopes(), running the whole scope
        // build twice per request.
        $missing_character_scopes = $this->isUserCompliantService->getMissingCharacterScopes($user);

        return $missing_character_scopes === []
            ? $next($request)
            : $this->redirectTo($missing_character_scopes);
    }

    /*
     * This method should return the user to a view where he needs to handle the addition of required scopes.
     *
     * Each entry is ['character' => CharacterInfo, 'required_scopes' => [...], 'missing_scopes' => [...]]
     * — previously this received getMissingScopes(), which had already dropped the character, so an
     * override could not tell which character needed which scope.
     */
    protected function redirectTo(array $missing_character_scopes): Response
    {
        return redirect('/');
    }
}
