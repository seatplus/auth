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

namespace Seatplus\Auth\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Seatplus\Eveapi\Models\Application;
use Seatplus\Eveapi\Models\Character\CharacterInfo;
use Spatie\Permission\Traits\HasRoles;

#[Fillable([
    'main_character_id', 'character_owner_hash', 'active',
])]
#[Hidden([
    'password', 'remember_token',
])]
class User extends Authenticatable
{
    use HasFactory;
    use HasRoles;

    /**
     * The primary key associated with the table.
     *
     * @var string
     */
    #[\Override]
    protected $primaryKey = 'id';

    /**
     * Indicates if the IDs are auto-incrementing.
     *
     * @var bool
     */
    #[\Override]
    public $incrementing = true;

    #[\Override]
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
        ];
    }

    /** @return HasMany<CharacterUser, $this> */
    public function characterUsers(): HasMany
    {
        return $this->hasMany(CharacterUser::class, 'user_id', 'id');
    }

    /** @return HasManyThrough<CharacterInfo, CharacterUser, $this> */
    public function characters(): HasManyThrough
    {
        return $this->hasManyThrough(
            CharacterInfo::class,
            CharacterUser::class,
            'user_id',
            'character_id',
            'id',
            'character_id'
        );
    }

    /** @return HasOne<CharacterInfo, $this> */
    public function mainCharacter(): HasOne
    {
        return $this->hasOne(CharacterInfo::class, 'character_id', 'main_character_id');
    }

    #[Scope]
    protected function search(Builder $query, string $query_string): Builder
    {
        return $query->whereHas('characters', function (Builder $query) use ($query_string) {
            $query->where('name', 'like', '%'.$query_string.'%');
        });
    }

    /** @return MorphOne<Application, $this> */
    public function application(): MorphOne
    {
        return $this->morphOne(Application::class, 'applicationable')->whereStatus('open');
    }

    #[\Override]
    public function getAuthPassword(): string
    {
        return '';
    }

    public function changeMainCharacter(int $character_id): bool
    {
        $this->main_character_id = $character_id;

        return $this->save();
    }
}
