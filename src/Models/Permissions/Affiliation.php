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

namespace Seatplus\Auth\Models\Permissions;

use Illuminate\Database\Eloquent\Attributes\Unguarded;
use Illuminate\Database\Eloquent\Attributes\WithoutIncrementing;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Seatplus\Eveapi\Models\Alliance\AllianceInfo;
use Seatplus\Eveapi\Models\Character\CharacterInfo;
use Seatplus\Eveapi\Models\Corporation\CorporationInfo;

/*
 * Seatplus\Auth\Models\Permissions\Affiliation
 *
 * @property string $type
 */
#[WithoutIncrementing]
#[Unguarded]
class Affiliation extends Model
{
    #[\Override]
    protected $primaryKey = null;

    #[\Override]
    protected function casts(): array
    {
        return [
            'role_id' => 'integer',
        ];
    }

    public function affiliatable(): MorphTo
    {
        return $this->morphTo();
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'role_id', 'id');
    }

    /**
     * @return Attribute<mixed, mixed>
     */
    protected function affiliatedIds(): Attribute
    {
        return new Attribute(
            get: fn () => match (true) {
                $this->affiliatable instanceof CharacterInfo => collect($this->affiliatable->character_id),
                $this->affiliatable instanceof CorporationInfo => collect([
                    $this->affiliatable->corporation_id,
                    $this->affiliatable->characters->pluck('character_id'),
                ])->flatten(),
                $this->affiliatable instanceof AllianceInfo => collect([
                    $this->affiliatable->alliance_id,
                    $this->affiliatable->corporations->pluck('corporation_id'),
                    $this->affiliatable->characters->pluck('character_id'),
                ])->flatten(),
                default => collect(),
            }
        );

    }
}
