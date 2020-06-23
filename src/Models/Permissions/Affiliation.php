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

namespace Seatplus\Auth\Models\Permissions;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Seatplus\Eveapi\Models\Character\CharacterInfo;

class Affiliation extends Model
{
    protected $primaryKey = null;

    /**
     * @var bool
     */
    public $incrementing = false;

    /**
     * @var bool
     */
    protected static $unguarded = true;

    /**
     * The attributes that should be cast to native types.
     *
     * @var array
     */
    protected $casts = [
        'role_id' => 'integer',
    ];

    public function affiliatable()
    {
        return $this->morphTo();
    }

    public function role()
    {
        return $this->belongsTo(Role::class, 'id', 'role_id');
    }

    public function getCharacterIdsAttribute(): Collection
    {
        return $this->affiliatable ? $this->affiliatable->characters->pluck('character_id') : collect();
    }

    public function getInverseCharacterIdsAttribute(): Collection
    {
        return CharacterInfo::query()
            ->whereNotIn('character_id', $this->getCharacterIdsAttribute()->toArray())
            ->pluck('character_id');
    }
}
