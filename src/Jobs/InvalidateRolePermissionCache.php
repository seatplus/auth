<?php

declare(strict_types=1);

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
