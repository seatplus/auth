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

namespace Seatplus\Auth\Tests\Unit\Models;

use Seatplus\Auth\Models\User;
use Seatplus\Auth\Tests\TestCase;
use Seatplus\Eveapi\Models\Character\CharacterInfo;

class UserTest extends TestCase
{
    /** @test */
    public function it_has_main_character_relationship()
    {
        $test_user = factory(User::class)->create();

        $this->assertInstanceOf(CharacterInfo::class, $test_user->main_character);
    }

    /** @test */
    public function it_has_characters_relationship()
    {
        $test_user = factory(User::class)->create();

        $this->assertDatabaseHas('character_users', [
            'character_id' => $test_user->character_users->first()->character_id,
        ]);

        factory(CharacterInfo::class)->create([
            'character_id' => $test_user->character_users->first()->character_id,
        ]);

        $this->assertDatabaseHas('character_infos', [
            'character_id' => $test_user->character_users->first()->character_id,
        ]);

        $this->assertInstanceOf(CharacterInfo::class, $test_user->characters->first());
    }

    /** @test */
    public function it_has_search_scope()
    {
        $test_user = factory(User::class)->create();

        factory(CharacterInfo::class)->create([
            'character_id' => $test_user->character_users->first()->character_id,
        ]);

        $character = $test_user->characters->first();

        $user = User::search($character->name)->first();

        $this->assertEquals($test_user->id, $user->id);
    }
}
