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

namespace Seatplus\Auth\Tests\Unit\Affiliations;

use Seatplus\Auth\Models\Permissions\Affiliation;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Tests\TestCase;
use Seatplus\Eveapi\Models\Character\CharacterAffiliation;
use Seatplus\Eveapi\Models\Character\CharacterInfo;

class SeatPlusRolesTest extends TestCase
{
    private $secondary_character;

    private $tertiary_character;

    public function setUp(): void
    {
        parent::setUp();

        $this->secondary_character = factory(CharacterInfo::class)->create();

        $this->tertiary_character = factory(CharacterInfo::class)->create();
    }

    /** @test */
    public function userHasNoRolesTest()
    {
        $this->assertTrue($this->test_user->roles->isEmpty());
    }

    /** @test */
    public function userHasRoleTest()
    {
        $role = Role::create(['name' => 'derp']);

        $this->test_user->assignRole($role);

        $this->assertTrue($this->test_user->roles->isNotEmpty());
    }

    /** @test */
    public function roleHasNoAffiliationTest()
    {
        $role = Role::create(['name' => 'derp']);

        $this->assertTrue($role->affiliations->isEmpty());
    }

    /** @test */
    public function roleHasAnAffiliationTest()
    {
        $role = Role::create(['name' => 'derp']);

        $role->affiliations()->create([
            'type' => 'allowed',
        ]);

        $this->assertNotNUll($role->affiliations);
    }

    /** @test */
    public function userIsInAffiliationTest()
    {
        $role = Role::create(['name' => 'derp']);

        $role->affiliations()->create([
            'character_id' => $this->test_character->character_id,
            'type'         => 'allowed',
        ]);

        $this->assertTrue($role->isAffiliated($this->test_character->character_id));
    }

    /** @test */
    public function characterIsInCharacterAllowedAffiliationTest()
    {
        $role = Role::create(['name' => 'derp']);

        $secondary_character = factory(CharacterAffiliation::class)->create();

        $role->affiliations()->createMany([
            [
                'character_id' => $this->test_character->character_id,
                'type'         => 'allowed',
            ],
            [
                'character_id' => $secondary_character->character_id,
                'type'         => 'allowed',
            ],

        ]);

        $this->assertTrue($role->isAffiliated($this->test_character->character_id));
        $this->assertTrue($role->isAffiliated($secondary_character->character_id));
    }

    /** @test */
    public function characterIsInCharacterInversedAffiliationTest()
    {
        $role = Role::create(['name' => 'derp']);

        $role->affiliations()->createMany([
            [
                'character_id' => $this->test_character->character_id,
                'type'         => 'inverse',
            ],
            [
                'character_id' => 1234,
                'type'         => 'inverse',
            ],
        ]);

        $this->assertFalse($role->isAffiliated($this->test_character->character_id));
    }

    /** @test */
    public function characterIsNotInCharacterInverseAffiliationTest()
    {
        $role = Role::create(['name' => 'derp']);

        $role->affiliations()->createMany([
            [
                'character_id' => $this->secondary_character->character_id,
                'type'         => 'inverse',
            ],
            [
                'character_id' => $this->tertiary_character->character_id,
                'type'         => 'inverse',
            ],
        ]);

        $this->assertTrue($role->isAffiliated($this->test_character->character_id));
        $this->assertFalse($role->isAffiliated($this->secondary_character->character_id));
        $this->assertFalse($role->isAffiliated($this->tertiary_character->character_id));
    }

    /** @test */
    public function characterIsInCharacterForbiddenAffiliationTest()
    {
        $role = Role::create(['name' => 'derp']);

        $role->affiliations()->createMany([
            [
                'character_id' => $this->test_character->character_id,
                'type'         => 'forbidden',
            ],
            [
                'character_id' => $this->secondary_character->character_id,
                'type'         => 'forbidden',
            ],
        ]);

        $this->assertFalse($role->isAffiliated($this->test_character->character_id));
        $this->assertFalse($role->isAffiliated($this->secondary_character->character_id));
        $this->assertFalse($role->isAffiliated($this->tertiary_character->character_id));
    }

    //TODO: Assertion that checks combination of forbidden character and allowed/inverse corporation

    // Corporation

    /** @test */
    public function characterIsInCorporationAllowedAffiliationTest()
    {
        $role = Role::create(['name' => 'derp']);

        $role->affiliations()->create([
            'corporation_id' => $this->test_character->corporation_id,
            'type'           => 'allowed',
        ]);

        $this->assertTrue($role->isAffiliated($this->test_character->character_id));
        $this->assertFalse($role->isAffiliated($this->secondary_character->character_id));
        $this->assertFalse($role->isAffiliated($this->tertiary_character->character_id));
    }

    /** @test */
    public function characterIsInCorporationInversedAffiliationTest()
    {
        $role = Role::create(['name' => 'derp']);

        $role->affiliations()->createMany([
            [
                'corporation_id' => $this->test_character->corporation_id,
                'type'           => 'inverse',
            ],
            [
                'corporation_id' => $this->secondary_character->corporation_id,
                'type'           => 'inverse',
            ],
        ]);

        //dump('-----------------------------');
        //dump($this->test_character->character_id, 'corp', $this->test_character->corporation_id);
        //dd($this->test_character->character_id, Affiliation::first()->characterAffiliations);

        $this->assertFalse($role->isAffiliated($this->test_character->character_id));
        $this->assertFalse($role->isAffiliated($this->secondary_character->character_id));
        $this->assertTrue($role->isAffiliated($this->tertiary_character->character_id));
    }

    /** @test */
    public function characterIsInCorporationForbiddenAffiliationTest()
    {
        $role = Role::create(['name' => 'derp']);

        $role->affiliations()->createMany([
            [
                'corporation_id' => $this->test_character->corporation_id,
                'type'           => 'forbidden',
            ],
            [
                'corporation_id' => $this->secondary_character->corporation_id,
                'type'           => 'forbidden',
            ],
        ]);

        $this->assertFalse($role->isAffiliated($this->test_character->character_id));
        $this->assertFalse($role->isAffiliated($this->secondary_character->character_id));
        $this->assertFalse($role->isAffiliated($this->tertiary_character->character_id));
    }

    // Alliance

    /** @test */
    public function characterIsInAllianceAllowedAffiliationTest()
    {
        $role = Role::create(['name' => 'derp']);

        $role->affiliations()->createMany([
            [
                'alliance_id' => $this->test_character->alliance_id,
                'type'        => 'allowed',
            ],
            [
                'alliance_id' => $this->secondary_character->alliance_id,
                'type'        => 'allowed',
            ],
        ]);

        $this->assertTrue($role->isAffiliated($this->test_character->character_id));
        $this->assertTrue($role->isAffiliated($this->secondary_character->character_id));
        $this->assertFalse($role->isAffiliated($this->tertiary_character->character_id));
    }

    /** @test */
    public function characterIsInAllianceInversedAffiliationTest()
    {
        $role = Role::create(['name' => 'derp']);

        $role->affiliations()->createMany([
            [
                'alliance_id' => $this->test_character->alliance_id,
                'type'        => 'inverse',
            ],
            [
                'alliance_id' => $this->secondary_character->alliance_id,
                'type'        => 'inverse',
            ],
        ]);

        $this->assertFalse($role->isAffiliated($this->test_character->character_id));
        $this->assertFalse($role->isAffiliated($this->secondary_character->character_id));
        $this->assertTrue($role->isAffiliated($this->tertiary_character->character_id));
    }

    /** @test */
    public function characterIsInAllianceForbiddenAffiliationTest()
    {
        $role = Role::create(['name' => 'derp']);

        $role->affiliations()->createMany([
            [
                'alliance_id' => $this->test_character->alliance_id,
                'type'        => 'forbidden',
            ],
            [
                'alliance_id' => $this->secondary_character->alliance_id,
                'type'        => 'forbidden',
            ],
        ]);

        $this->assertFalse($role->isAffiliated($this->test_character->character_id));
        $this->assertFalse($role->isAffiliated($this->secondary_character->character_id));
        $this->assertFalse($role->isAffiliated($this->tertiary_character->character_id));
    }
}
