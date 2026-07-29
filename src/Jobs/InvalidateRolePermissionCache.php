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

namespace Seatplus\Auth\Jobs;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Seatplus\Auth\Models\User;
use Seatplus\Auth\Services\Permissions\CanUserService;

/**
 * Forgets the cached permission object of every user currently holding a role.
 *
 * A role's affiliation set is embedded, per permission, in the `user_permissions_{id}`
 * cache. That set is not recomputed until the 5-minute TTL lapses, so an affiliation
 * edit would otherwise stay invisible for up to five minutes. This job is dispatched
 * from the affiliation mutation site to drop the stale entries immediately; the next
 * request rebuilds them. It is intentionally scoped to a single role and made unique
 * per role so a burst of admin edits coalesces into one run.
 */
class InvalidateRolePermissionCache implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(
        public readonly int $roleId,
    ) {}

    public function uniqueId(): string
    {
        return (string) $this->roleId;
    }

    /**
     * @return array<int, string>
     */
    public function tags(): array
    {
        return [
            'Invalidate Role Permission Cache',
            "role_id:{$this->roleId}",
        ];
    }

    public function handle(): void
    {
        User::query()
            ->whereHas('roles', fn (Builder $query) => $query->where('id', $this->roleId))
            ->pluck('id')
            ->each(fn (int $userId) => Cache::forget(CanUserService::userPermissionCacheKey($userId)));
    }
}
