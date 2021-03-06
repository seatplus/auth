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

namespace Seatplus\Auth\Models\Permissions;

use Exception;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Collection;
use Seatplus\Auth\Models\AccessControl\AclAffiliation;
use Seatplus\Auth\Models\AccessControl\AclMember;
use Seatplus\Auth\Models\User;
use Seatplus\Eveapi\Models\Alliance\AllianceInfo;
use Seatplus\Eveapi\Models\Corporation\CorporationInfo;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    public function affiliations()
    {
        return $this->hasMany(Affiliation::class, 'role_id');
    }

    public function acl_affiliations()
    {
        return $this->hasMany(AclAffiliation::class, 'role_id')
            ->where('can_moderate', false);
    }

    public function moderators()
    {
        return $this->hasMany(AclAffiliation::class, 'role_id')
            ->where('can_moderate', true);
    }

    public function acl_members()
    {
        return $this->hasMany(AclMember::class, 'role_id');
    }

    public function members()
    {
        return $this->acl_members()
            ->whereStatus('member');
    }

    public function activateMember(User $user): void
    {
        if (in_array($this->type, ['automatic', 'opt-in', 'on-request'])) {
            if ($user->characters->pluck('character_id')->intersect($this->getAclAffiliatedIdsAttribute())->isEmpty()) {
                throw new Exception('User is not allowed for this access control group');
            }
        }

        AclMember::query()->updateOrInsert(
            ['role_id' => $this->id, 'user_id' => $user->getAuthIdentifier()],
            ['status' => 'member']
        );

        $user->assignRole($this);
    }

    public function joinWaitlist(User $user): void
    {
        if ($this->type !== 'on-request') {
            throw new Exception('Only on-request control groups do have a waitlist');
        }

        if ($user->characters->pluck('character_id')->intersect($this->getAclAffiliatedIdsAttribute())->isEmpty()) {
            throw new Exception('User is not allowed for this access control group');
        }

        AclMember::query()->updateOrInsert(
            ['role_id' => $this->id, 'user_id' => $user->getAuthIdentifier()],
            ['status' => 'waitlist']
        );
    }

    public function pauseMember(User $user): void
    {
        AclMember::where('user_id', $user->getAuthIdentifier())
            ->where('role_id', $this->id)
            ->where('status', 'member')
            ->update(['status' => 'paused']);

        $user->removeRole($this);
    }

    public function removeMember(User $user): void
    {
        AclMember::where('user_id', $user->getAuthIdentifier())
            ->where('role_id', $this->id)
            ->where('status', 'member')
            ->delete();

        $user->removeRole($this);
    }

    public function isModerator(User $user): bool
    {
        return $user->characters
            ->pluck('character_id')
            ->intersect($this->getModeratorIdsAttribute())
            ->isNotEmpty();
    }

    /**
     * @return array
     */
    public function getAffiliatedIdsAttribute(): array
    {
        //eager load relations for preventing n+1 queries
        $role_with_relationships = $this->loadMissing([
            'affiliations.affiliatable' => fn (MorphTo $morph_to) => $morph_to->morphWith([CorporationInfo::class => 'characters', AllianceInfo::class => ['characters', 'corporations']]),
        ]);

        return $role_with_relationships->getAffiliatedIds()
            ->diff($role_with_relationships->getForbiddenAndInverseIds()->toArray())
            ->all();
    }

    /**
     * @return array
     */
    public function getAclAffiliatedIdsAttribute(): array
    {
        $acl_affiliations = $this->acl_affiliations()
            ->with(
                ['affiliatable' => function (MorphTo $morph_to) {
                    $morph_to->morphWith([CorporationInfo::class => 'characters', AllianceInfo::class => 'characters']);
                }]
            )
            ->cursor();

        return $acl_affiliations
            ->map(fn ($affiliation) => $affiliation->character_ids)
            ->flatten()
            ->unique()
            ->toArray();
    }

    /**
     * @return array
     */
    public function getModeratorIdsAttribute(): array
    {
        //eager load relations for preventing n+1 queries
        $role_with_relationships = $this->loadMissing([
            'moderators.affiliatable' => fn (MorphTo $morph_to) => $morph_to->morphWith([CorporationInfo::class => 'characters', AllianceInfo::class => 'characters']),
        ]);

        return $role_with_relationships->moderators
            ->map(fn ($affiliation) => $affiliation->character_ids)
            ->flatten()
            ->unique()
            ->toArray();
    }

    private function getAffiliatedIds(): Collection
    {
        return $this->affiliations
            ->reject(fn ($affiliation) => $affiliation->type === 'forbidden')
            // TODO get IDs instead of character_ids
            ->map(fn ($affiliation) => $affiliation->type === 'allowed' ? $affiliation->affiliated_ids : $affiliation->inverse_affiliated_ids)
            ->flatten()
            ->unique();
    }

    private function getForbiddenAndInverseIds(): Collection
    {
        return $this->affiliations
            // we are only concerned about forbidden and inverse ids
            ->reject(fn ($affiliation) => $affiliation->type === 'allowed')
            ->map(fn ($affiliation) => $affiliation->affiliated_ids)
            ->flatten()
            ->unique();
    }

    public function delete()
    {
        $this->affiliations()->delete();
        $this->acl_affiliations()->delete();

        return parent::delete();
    }
}
