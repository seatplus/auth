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

namespace Seatplus\Auth\Tests\Unit\Actions;

use Faker\Factory;
use Seatplus\Auth\Actions\GetAffiliatedCharactersIdsByPermissionArray;
use Seatplus\Auth\Models\Permissions\Permission;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Tests\TestCase;
use Seatplus\Eveapi\Models\Character\CharacterInfo;

class GetAffiliatedCharacterIdsByPermissionArrayTest extends TestCase
{
    protected $role;

    protected $permission;

    protected $test_character_user;

    public $test_character;

    private $secondary_character;

    private $tertiary_character;

    protected function setUp(): void
    {
        parent::setUp();

        $this->role = Role::create(['name' => 'writer']);
        $this->permission = Permission::create(['name' => 'edit articles']);

        $this->role->givePermissionTo($this->permission);
        $this->test_user->assignRole($this->role);

        $this->test_character_user = $this->test_user->character_users->first();

        $this->actingAs($this->test_user);

        $this->test_character = factory(CharacterInfo::class)->create([
            'character_id' => $this->test_character_user->character_id,
        ]);

        $this->secondary_character = factory(CharacterInfo::class)->create();

        $this->tertiary_character = factory(CharacterInfo::class)->create();
    }

    /**
     * @test
     *
     * @throws \Exception
     */
    public function it_returns_own_character_id()
    {
        $this->role->affiliations()->create([
            'character_id' => $this->test_character->character_id,
            'type'         => 'allowed',
        ]);

        $ids = (new GetAffiliatedCharactersIdsByPermissionArray($this->permission->name))->execute();

        $this->assertTrue(in_array($this->test_character_user->character_id, $ids));
    }

    /** @test */
    public function it_returns_other_and_own_character_id_for_inverted()
    {
        $this->role->affiliations()->create([
            'character_id' => $this->test_character->character_id,
            'type'         => 'inverse',
        ]);

        $ids = (new GetAffiliatedCharactersIdsByPermissionArray($this->permission->name))->execute();

        $this->assertTrue(in_array($this->test_character_user->character_id, $ids));
        $this->assertTrue(in_array($this->secondary_character->character_id, $ids));
    }

    /** @test */
    public function it_does_not_return_secondary_character_id_if_secondary_character_is_inverted()
    {
        $this->role->affiliations()->create([
            'character_id' => $this->secondary_character->character_id,
            'type'         => 'inverse',
        ]);

        $ids = (new GetAffiliatedCharactersIdsByPermissionArray($this->permission->name))->execute();

        // Assert that the owned character_ids are present
        $this->assertTrue(in_array($this->test_character_user->character_id, $ids));

        // Assert that ids from the inverted corporation is missing
        $this->assertFalse(in_array($this->secondary_character->character_id, $ids));

        // Assert that ids from any other third party is present
        $this->assertTrue(in_array($this->tertiary_character->character_id, $ids));
    }

    /** @test */
    public function it_does_not_return_secondary_character_id_if_secondary_corporation_is_inverted()
    {
        $this->role->affiliations()->create([
            'corporation_id' => $this->secondary_character->corporation->corporation_id,
            'type'           => 'inverse',
        ]);

        $ids = (new GetAffiliatedCharactersIdsByPermissionArray($this->permission->name))->execute();

        // Assert that second character does not share same corporation as the first character
        $this->assertNotEquals($this->secondary_character->corporation->corporation_id, $this->test_character->corporation->corporation_id);

        // Assert that the owned character_ids are present
        $this->assertTrue(in_array($this->test_character_user->character_id, $ids));

        // Assert that ids from the inverted corporation is missing
        $this->assertFalse(in_array($this->secondary_character->character_id, $ids));

        // Assert that ids from any other third party is present
        $this->assertTrue(in_array($this->tertiary_character->character_id, $ids));
    }

    /** @test */
    public function it_does_not_return_secondary_character_id_if_secondary_alliance_is_inverted()
    {
        $this->role->affiliations()->create([
            'alliance_id' => $this->secondary_character->alliance->alliance_id,
            'type'        => 'inverse',
        ]);

        $ids = (new GetAffiliatedCharactersIdsByPermissionArray($this->permission->name))->execute();

        // Assert that second character does not share same corporation as the first character
        $this->assertNotEquals($this->secondary_character->alliance->alliance_id, $this->test_character->alliance->alliance_id);

        // Assert that the owned character_ids are present
        $this->assertTrue(in_array($this->test_character_user->character_id, $ids));

        // Assert that ids from the inverted corporation is missing
        $this->assertFalse(in_array($this->secondary_character->character_id, $ids));

        // Assert that ids from any other third party is present
        $this->assertTrue(in_array($this->tertiary_character->character_id, $ids));
    }

    /** @test */
    public function it_does_return_secondary_character_id_if_secondary_character_is_allowed()
    {
        $this->role->affiliations()->create([
            'character_id' => $this->secondary_character->character_id,
            'type'         => 'allowed',
        ]);

        $ids = (new GetAffiliatedCharactersIdsByPermissionArray($this->permission->name))->execute();

        // Assert that second character does not share same corporation as the first character
        $this->assertNotEquals($this->secondary_character->character_id, $this->test_character->character_id);

        // Assert that the owned character_ids are present
        $this->assertTrue(in_array($this->test_character_user->character_id, $ids));

        // Assert that ids from the allowed character is present
        $this->assertTrue(in_array($this->secondary_character->character_id, $ids));

        // Assert that ids from any other third party is not present
        $this->assertFalse(in_array($this->tertiary_character->character_id, $ids));
    }

    /** @test */
    public function it_does_return_secondary_character_id_if_secondary_corporation_is_allowed()
    {
        $this->role->affiliations()->create([
            'corporation_id' => $this->secondary_character->corporation->corporation_id,
            'type'           => 'allowed',
        ]);

        $ids = (new GetAffiliatedCharactersIdsByPermissionArray($this->permission->name))->execute();

        // Assert that second character does not share same corporation as the first character
        $this->assertNotEquals($this->secondary_character->corporation->corporation_id, $this->test_character->corporation->corporation_id);

        // Assert that the owned character_ids are present
        $this->assertTrue(in_array($this->test_character_user->character_id, $ids));

        // Assert that ids from the allowed character is present
        $this->assertTrue(in_array($this->secondary_character->character_id, $ids));

        // Assert that ids from any other third party is not present
        $this->assertFalse(in_array($this->tertiary_character->character_id, $ids));
    }

    /** @test */
    public function it_does_return_secondary_character_id_if_secondary_alliance_is_allowed()
    {
        $this->role->affiliations()->create([
            'alliance_id' => $this->secondary_character->alliance->alliance_id,
            'type'        => 'allowed',
        ]);

        $ids = (new GetAffiliatedCharactersIdsByPermissionArray($this->permission->name))->execute();

        // Assert that second character does not share same corporation as the first character
        $this->assertNotEquals($this->secondary_character->alliance->alliance_id, $this->test_character->alliance->alliance_id);

        // Assert that the owned character_ids are present
        $this->assertTrue(in_array($this->test_character_user->character_id, $ids));

        // Assert that ids from the allowed character is present
        $this->assertTrue(in_array($this->secondary_character->character_id, $ids));

        // Assert that ids from any other third party is not present
        $this->assertFalse(in_array($this->tertiary_character->character_id, $ids));
    }

    /** @test  */
    public function it_does_return_own_character_even_if_listed_as_forbidden()
    {
        $this->role->affiliations()->create([
            'character_id' => $this->secondary_character->character_id,
            'type'         => 'forbidden',
        ]);

        $ids = (new GetAffiliatedCharactersIdsByPermissionArray($this->permission->name))->execute();

        // Assert that the owned character_ids are present
        $this->assertTrue(in_array($this->test_character_user->character_id, $ids));
    }

    /** @test */
    public function it_does_not_return_secondary_character_id_if_secondary_character_is_forbidden()
    {
        $this->role->affiliations()->createMany([
            [
                'character_id' => $this->secondary_character->character_id,
                'type'         => 'allowed',
            ],
            [
                'character_id' => $this->secondary_character->character_id,
                'type'         => 'forbidden',
            ],
        ]);

        $ids = (new GetAffiliatedCharactersIdsByPermissionArray($this->permission->name))->execute();

        // Assert that second character does not share same corporation as the first character
        $this->assertNotEquals($this->secondary_character->character_id, $this->test_character->character_id);

        // Assert that the owned character_ids are present
        $this->assertTrue(in_array($this->test_character_user->character_id, $ids));

        // Assert that ids from the allowed character is not present
        $this->assertFalse(in_array($this->secondary_character->character_id, $ids));

        // Assert that ids from any other third party is not present
        $this->assertFalse(in_array($this->tertiary_character->character_id, $ids));
    }

    /** @test */
    public function it_does_not_return_secondary_character_id_if_secondary_corporation_is_forbidden()
    {
        $this->role->affiliations()->createMany([
            [
                'corporation_id' => $this->secondary_character->corporation->corporation_id,
                'type'           => 'allowed',
            ],
            [
                'corporation_id' => $this->secondary_character->corporation->corporation_id,
                'type'           => 'forbidden',
            ],
        ]);

        $ids = (new GetAffiliatedCharactersIdsByPermissionArray($this->permission->name))->execute();

        // Assert that second character does not share same corporation as the first character
        $this->assertNotEquals($this->secondary_character->corporation->corporation_id, $this->test_character->corporation->corporation_id);

        // Assert that the owned character_ids are present
        $this->assertTrue(in_array($this->test_character_user->character_id, $ids));

        // Assert that ids from the allowed character is not present
        $this->assertFalse(in_array($this->secondary_character->character_id, $ids));

        // Assert that ids from any other third party is not present
        $this->assertFalse(in_array($this->tertiary_character->character_id, $ids));
    }

    /** @test */
    public function it_does_not_return_secondary_character_id_if_secondary_alliance_is_forbidden()
    {
        $this->role->affiliations()->createMany([
            [
                'alliance_id' => $this->secondary_character->alliance->alliance_id,
                'type'        => 'allowed',
            ],
            [
                'alliance_id' => $this->secondary_character->alliance->alliance_id,
                'type'        => 'forbidden',
            ],
        ]);

        $ids = (new GetAffiliatedCharactersIdsByPermissionArray($this->permission->name))->execute();

        // Assert that second character does not share same corporation as the first character
        $this->assertNotEquals($this->secondary_character->alliance->alliance_id, $this->test_character->alliance->alliance_id);

        // Assert that the owned character_ids are present
        $this->assertTrue(in_array($this->test_character_user->character_id, $ids));

        // Assert that ids from the allowed character is not present
        $this->assertFalse(in_array($this->secondary_character->character_id, $ids));

        // Assert that ids from any other third party is not present
        $this->assertFalse(in_array($this->tertiary_character->character_id, $ids));
    }

    /** @test */
    public function it_does_work_with_model_method()
    {
        $this->role->affiliations()->createMany([
            [
                'alliance_id' => $this->secondary_character->alliance->alliance_id,
                'type'        => 'allowed',
            ],
            [
                'alliance_id' => $this->secondary_character->alliance->alliance_id,
                'type'        => 'forbidden',
            ],
        ]);

        $ids = $this->test_user->getAffiliatedCharacterIdsByPermission($this->permission);

        // Assert that second character does not share same corporation as the first character
        $this->assertNotEquals($this->secondary_character->alliance->alliance_id, $this->test_character->alliance->alliance_id);

        // Assert that the owned character_ids are present
        $this->assertTrue(in_array($this->test_character_user->character_id, $ids));

        // Assert that ids from the allowed character is not present
        $this->assertFalse(in_array($this->secondary_character->character_id, $ids));

        // Assert that ids from any other third party is not present
        $this->assertFalse(in_array($this->tertiary_character->character_id, $ids));
    }

    /** @test */
    public function it_caches_results()
    {
        $this->role->affiliations()->createMany([
            [
                'alliance_id' => $this->secondary_character->alliance->alliance_id,
                'type'        => 'allowed',
            ],
            [
                'alliance_id' => $this->secondary_character->alliance->alliance_id,
                'type'        => 'forbidden',
            ],
        ]);

        $action = new GetAffiliatedCharactersIdsByPermissionArray($this->permission->name);

        $this->assertFalse(cache()->has($action->getCacheKey()));

        $ids = $action->execute();

        $this->assertTrue(cache()->has($action->getCacheKey()));
    }

    /** @test */
    public function it_creates_permission_if_not_existing()
    {
        $faker = Factory::create();

        $permission_name = $faker->name;

        $action = new GetAffiliatedCharactersIdsByPermissionArray($permission_name);

        $action->execute();

        $this->assertNotNull(Permission::findByName($permission_name));
    }
}
