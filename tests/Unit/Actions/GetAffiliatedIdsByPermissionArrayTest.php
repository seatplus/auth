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

use Illuminate\Support\Facades\Event;
use Seatplus\Auth\Actions\GetAffiliatedIdsByPermissionArray;
use Seatplus\Auth\Models\Permissions\Permission;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Tests\TestCase;
use Seatplus\Eveapi\Models\Alliance\AllianceInfo;
use Seatplus\Eveapi\Models\Character\CharacterAffiliation;
use Seatplus\Eveapi\Models\Character\CharacterInfo;
use Seatplus\Eveapi\Models\Character\CharacterRole;
use Seatplus\Eveapi\Models\Corporation\CorporationInfo;
use Seatplus\Eveapi\Tests\Unit\Services\Jobs\Alliance\AllianceInfoActionTest;

class GetAffiliatedIdsByPermissionArrayTest extends TestCase
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

        Event::fakeFor(function () {

            $this->secondary_character = CharacterInfo::factory()->create();

            $this->tertiary_character = CharacterInfo::factory()->create();
        });

    }

    /**
     * @test
     *
     * @throws \Exception
     */
    public function it_returns_own_character_id()
    {
       $this->role->affiliations()->create([
            'affiliatable_id' => $this->test_character->character_id,
            'affiliatable_type' => CharacterInfo::class,
            'type'         => 'allowed',
        ]);

        $ids = (new GetAffiliatedIdsByPermissionArray($this->permission->name))->execute();

        $this->assertTrue(in_array($this->test_character_user->character_id, $ids));
    }

    /** @test */
    public function it_returns_other_and_own_character_id_for_inverted()
    {
        $this->role->affiliations()->create([
            'affiliatable_id' => $this->test_character->character_id,
            'affiliatable_type' => CharacterInfo::class,
            'type'         => 'inverse',
        ]);

        $ids = (new GetAffiliatedIdsByPermissionArray($this->permission->name))->execute();

        $this->assertTrue(in_array($this->test_character_user->character_id, $ids));
        $this->assertTrue(in_array($this->secondary_character->character_id, $ids));
    }

    /** @test */
    public function it_does_not_return_secondary_character_id_if_secondary_character_is_inverted()
    {
        $this->role->affiliations()->create([
            'affiliatable_id' => $this->secondary_character->character_id,
            'affiliatable_type' => CharacterInfo::class,
            'type'         => 'inverse',
        ]);

        $ids = (new GetAffiliatedIdsByPermissionArray($this->permission->name))->execute();

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
            'affiliatable_id' => $this->secondary_character->corporation->corporation_id,
            'affiliatable_type' => CorporationInfo::class,
            'type'           => 'inverse',
        ]);

        $ids = (new GetAffiliatedIdsByPermissionArray($this->permission->name))->execute();

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
            'affiliatable_id' => $this->secondary_character->alliance->alliance_id,
            'affiliatable_type' => AllianceInfo::class,
            'type'        => 'inverse',
        ]);

        $ids = (new GetAffiliatedIdsByPermissionArray($this->permission->name))->execute();

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
            'affiliatable_id' => $this->secondary_character->character_id,
            'affiliatable_type' => CharacterInfo::class,
            'type'         => 'allowed',
        ]);

        $ids = (new GetAffiliatedIdsByPermissionArray($this->permission->name))->execute();

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
            'affiliatable_id' => $this->secondary_character->corporation->corporation_id,
            'affiliatable_type' => CorporationInfo::class,
            'type'           => 'allowed',
        ]);

        $ids = (new GetAffiliatedIdsByPermissionArray($this->permission->name))->execute();

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
            'affiliatable_id' => $this->secondary_character->alliance->alliance_id,
            'affiliatable_type' => AllianceInfo::class,
            'type'        => 'allowed',
        ]);

        $ids = (new GetAffiliatedIdsByPermissionArray($this->permission->name))->execute();

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
            'affiliatable_id' => $this->secondary_character->character_id,
            'affiliatable_type' => CharacterInfo::class,
            'type'         => 'forbidden',
        ]);

        $ids = (new GetAffiliatedIdsByPermissionArray($this->permission->name))->execute();

        // Assert that the owned character_ids are present
        $this->assertTrue(in_array($this->test_character_user->character_id, $ids));
    }

    /** @test */
    public function it_does_not_return_secondary_character_id_if_secondary_character_is_forbidden()
    {
        $this->role->affiliations()->createMany([
            [
                'affiliatable_id' => $this->secondary_character->character_id,
                'affiliatable_type' => CharacterInfo::class,
                'type'         => 'allowed',
            ],
            [
                'affiliatable_id' => $this->secondary_character->character_id,
                'affiliatable_type' => CharacterInfo::class,
                'type'         => 'forbidden',
            ],
        ]);

        $ids = (new GetAffiliatedIdsByPermissionArray($this->permission->name))->execute();

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
                'affiliatable_id' => $this->secondary_character->corporation->corporation_id,
                'affiliatable_type' => CorporationInfo::class,
                'type'           => 'allowed',
            ],
            [
                'affiliatable_id' => $this->secondary_character->corporation->corporation_id,
                'affiliatable_type' => CorporationInfo::class,
                'type'           => 'forbidden',
            ],
        ]);

        $ids = (new GetAffiliatedIdsByPermissionArray($this->permission->name))->execute();

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
                'affiliatable_id' => $this->secondary_character->alliance->alliance_id,
                'affiliatable_type' => AllianceInfo::class,
                'type'        => 'allowed',
            ],
            [
                'affiliatable_id' => $this->secondary_character->alliance->alliance_id,
                'affiliatable_type' => AllianceInfo::class,
                'type'        => 'forbidden',
            ],
        ]);

        $ids = (new GetAffiliatedIdsByPermissionArray($this->permission->name))->execute();

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
                'affiliatable_id' => $this->secondary_character->alliance->alliance_id,
                'affiliatable_type' => AllianceInfo::class,
                'type'        => 'allowed',
            ],
            [
                'affiliatable_id' => $this->secondary_character->alliance->alliance_id,
                'affiliatable_type' => AllianceInfo::class,
                'type'        => 'forbidden',
            ],
        ]);

        $action = new GetAffiliatedIdsByPermissionArray($this->permission->name);

        $this->assertFalse(cache()->has($action->getCacheKey()));

        $ids = $action->execute();

        $this->assertTrue(cache()->has($action->getCacheKey()));
        $this->assertEquals($ids, cache($action->getCacheKey()));
    }

    /** @test */
    public function it_returns_corporation_id()
    {
        // first make sure test_character corporation is in the alliance
        $corporation = $this->test_character->corporation;
        $corporation->alliance_id = $this->test_character->alliance->alliance_id;
        $corporation->save();

        // create role affiliation on alliance level
        $this->role->affiliations()->create([
            'affiliatable_id' => $this->test_character->alliance->alliance_id,
            'affiliatable_type' => AllianceInfo::class,
            'type'        => 'allowed',
        ]);

        // Create director role for corporation
        $character_role = CharacterRole::factory()->make([
            'character_id' => $this->test_character->character_id,
            'roles' => ['Contract_Manager', 'Director']
        ]);

        $ids = (new GetAffiliatedIdsByPermissionArray($this->permission->name, 'Director'))->execute();

        // Assert that the owned character_ids are present
        $this->assertTrue(in_array($this->test_character->character_id, $ids));

        // Assert that the corporation_id of test_character with director role is present
        $this->assertTrue(in_array($this->test_character->corporation->corporation_id, $ids));
    }

    /** @test */
    public function it_returns_all_character_and_corporation_ids_for_superuser()
    {
        // give test user superuser
        Permission::create(['name' => 'superuser']);
        $this->test_user->givePermissionTo('superuser');

        // collect all corporation_ids
        $corporation_ids = CorporationInfo::all()->pluck('corporation_id')->values();

        // collect all character_ids
        $character_ids = CharacterInfo::all()->pluck('character_id')->values();

        // get ids
        $ids = (new GetAffiliatedIdsByPermissionArray($this->permission->name))->execute();

        // check if ids are present
        $this->assertTrue(collect([...$character_ids, ...$corporation_ids])->diff($ids)->isEmpty());
    }
}
