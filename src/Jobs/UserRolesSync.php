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

namespace Seatplus\Auth\Jobs;

use Exception;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\LazyCollection;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Models\User;
use Seatplus\Eveapi\Models\Character\CharacterInfo;

class UserRolesSync implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 1;

    private array $character_ids;

    public function uniqueId(): string
    {
        return implode(', ', $this->tags());
    }

    /**
     * The number of seconds after which the job's unique lock will be released.
     *
     * @var int
     */
    public int $uniqueFor = 3600;

    public function __construct(
        private User $user
    ) {
        $this->character_ids = User::query()
            ->has('characters.refresh_token')
            ->with(['characters.refresh_token' => fn (HasOne $query) => $query->select('character_id')])
            ->whereId($this->user->id)
            ->get()
            ->whenNotEmpty(function (Collection $collection) {
                return $collection->first()->characters->map(fn (CharacterInfo $character) => $character->character_id);
            })
            ->toArray();
    }

    /**
     * Assign this job a tag so that Horizon can categorize and allow
     * for specific tags to be monitored.
     *
     * If a job specifies the tags property, that is added.
     *
     * @return array
     */
    public function tags(): array
    {
        return [
            'Roles sync',
            "user_id: {$this->user->id}",
            'main_character: ' . ($this->user->main_character->name ?? ''),
        ];
    }

    public function handle(): void
    {
        $this->handleAutomaticRoles();
        $this->handleOtherRoles();
    }

    private function handleAutomaticRoles(): void
    {
        $automatic_roles = Role::has('acl_affiliations')
            ->whereType('automatic')
            ->with('acl_affiliations.affiliatable.characters')
            ->cursor();

        $this->handleMemberships($automatic_roles);
    }

    private function handleOtherRoles(): void
    {
        $roles = Role::query()
            ->has('acl_affiliations')
            ->with('acl_affiliations.affiliatable.characters')
            ->whereNotIn('type', ['manual', 'automatic'])
            ->whereHas(
                'acl_members',
                fn (Builder $query) => $query->where('user_id', $this->user->getAuthIdentifier())
                    ->whereIn('status', ['member', 'paused'])
            )
            ->cursor();

        $this->handleMemberships($roles);
    }

    private function handleMemberships(LazyCollection $roles): void
    {
        foreach ($roles as $role) {
            collect($this->character_ids)->intersect($role->acl_affiliated_ids)->isNotEmpty()
                ? $role->activateMember($this->user)
                : $role->pauseMember($this->user);
        }
    }
}
