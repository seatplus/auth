<?php

/*
 * MIT License
 *
 * Copyright (c) 2019, 2020 Felix Huber
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
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Models\User;
use Seatplus\Eveapi\Jobs\Middleware\RateLimitedJobMiddleware;

class UserRolesSync implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 1;

    private array $character_ids;

    /**
     * Get the middleware the job should pass through.
     *
     * @return array
     */
    public function middleware(): array
    {
        $rate_limited_middleware = (new RateLimitedJobMiddleware)
            ->setKey(self::class)
            ->setViaCharacterId($this->user->id)
            ->setDuration(3600);

        return [
            $rate_limited_middleware,
        ];
    }

    public function __construct(/**
     * @var \Seatplus\Auth\Models\User
     */
    private User $user)
    {
        $this->character_ids = User::has('characters.refresh_token')
            ->with(['characters.refresh_token' => fn ($query) => $query->select('character_id')])
            ->whereId($this->user->id)
            ->get()
            ->whenNotEmpty(function ($collection) {
                return $collection->first()->characters->map(fn ($character) => $character->refresh_token->character_id);
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
    public function tags()
    {
        return [
            'Roles sync',
            sprintf('user_id: %s', $this->user->id),
            sprintf('main_character: %s', $this->user->main_character->name ?? ''),
        ];
    }

    public function handle()
    {
        try {
            $this->handleAutomaticRoles();
            $this->handleOtherRoles();
        } catch (Exception $exception) {
            throw $exception;
        }
    }

    private function handleAutomaticRoles()
    {
        $automatic_roles = Role::has('acl_affiliations')
            ->whereType('automatic')
            ->with('acl_affiliations.affiliatable.characters')
            ->cursor();

        $this->handleMemberships($automatic_roles);
    }

    private function handleOtherRoles()
    {
        $roles = Role::has('acl_affiliations')
            ->with('acl_affiliations.affiliatable.characters')
            ->whereNotIn('type', ['manual', 'automatic'])
            ->whereHas('acl_members', fn (Builder $query) => $query->where('user_id', $this->user->getAuthIdentifier())
                ->whereIn('status', ['member', 'paused'])
            )
            ->cursor();

        $this->handleMemberships($roles);
    }

    private function handleMemberships($roles)
    {
        foreach ($roles as $role) {
            collect($this->character_ids)->intersect($role->acl_affiliated_ids)->isNotEmpty()
                ? $role->activateMember($this->user)
                : $role->pauseMember($this->user);
        }
    }
}
