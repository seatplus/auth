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

use Illuminate\Support\Facades\Event;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Tests\TestCase;
use Seatplus\Eveapi\Models\Alliance\AllianceInfo;
use Seatplus\Eveapi\Models\Character\CharacterInfo;
use Seatplus\Eveapi\Models\Corporation\CorporationInfo;

class SeatPlusRolesTest extends TestCase
{
    private $secondary_character;

    private $tertiary_character;

    private Role $role;

    public function setUp(): void
    {
        parent::setUp();

        Event::fake();

        $this->secondary_character = factory(CharacterInfo::class)->create();

        $this->tertiary_character = factory(CharacterInfo::class)->create();

        $this->role = Role::create(['name' => 'derp']);
    }

    /** @test */
    public function userHasNoRolesTest()
    {
        $this->assertTrue($this->test_user->roles->isEmpty());
    }

    /** @test */
    public function userHasRoleTest()
    {
        $this->test_user->assignRole($this->role);

        $this->assertTrue($this->test_user->roles->isNotEmpty());
    }

    /** @test */
    public function roleHasNoAffiliationTest()
    {
        $this->assertTrue($this->role->affiliations->isEmpty());
    }

    /** @test */
    public function roleHasAnAffiliationTest()
    {
        $this->role->affiliations()->create([
            'affiliatable_id'   => $this->test_character->character_id,
            'affiliatable_type' => CharacterInfo::class,
            'type'              => 'allowed',
        ]);

        $this->assertNotNUll($this->role->affiliations);
    }

    /** @test */
    public function userIsInAffiliationTest()
    {
        $this->role->affiliations()->create([
            'affiliatable_id'   => $this->test_character->character_id,
            'affiliatable_type' => CharacterInfo::class,
            'type'              => 'allowed',
        ]);

        $this->assertTrue(in_array($this->test_character->character_id, $this->role->affiliated_ids));
    }

    /** @test */
    public function characterIsInCharacterAllowedAffiliationTest()
    {
        $secondary_character = factory(CharacterInfo::class)->create();

        $this->role->affiliations()->createMany([
            [
                'affiliatable_id'   => $this->test_character->character_id,
                'affiliatable_type' => CharacterInfo::class,
                'type'              => 'allowed',
            ],
            [
                'affiliatable_id'   => $secondary_character->character_id,
                'affiliatable_type' => CharacterInfo::class,
                'type'              => 'allowed',
            ],

        ]);

        $this->assertTrue(in_array($this->test_character->character_id, $this->role->affiliated_ids));
        $this->assertTrue(in_array($secondary_character->character_id, $this->role->affiliated_ids));
    }

    /** @test */
    public function characterIsInCharacterInversedAffiliationTest()
    {
        $this->role->affiliations()->createMany([
            [
                'affiliatable_id'   => $this->test_character->character_id,
                'affiliatable_type' => CharacterInfo::class,
                'type'              => 'inverse',
            ],
            [
                'affiliatable_id'   => 1234,
                'affiliatable_type' => CharacterInfo::class,
                'type'              => 'inverse',
            ],
        ]);

        $this->assertFalse(in_array($this->test_character->character_id, $this->role->affiliated_ids));
    }

    /** @test */
    public function characterIsNotInCharacterInverseAffiliationTest()
    {
        $this->role->affiliations()->createMany([
            [
                'affiliatable_id'   => $this->secondary_character->character_id,
                'affiliatable_type' => CharacterInfo::class,
                'type'              => 'inverse',
            ],
            [
                'affiliatable_id'   => $this->tertiary_character->character_id,
                'affiliatable_type' => CharacterInfo::class,
                'type'              => 'inverse',
            ],
        ]);

        $this->assertTrue(in_array($this->test_character->character_id, $this->role->affiliated_ids));
        $this->assertFalse(in_array($this->secondary_character->character_id, $this->role->affiliated_ids));
        $this->assertFalse(in_array($this->tertiary_character->character_id, $this->role->affiliated_ids));
    }

    /** @test */
    public function characterIsInCharacterForbiddenAffiliationTest()
    {
        $this->role->affiliations()->createMany([
            [
                'affiliatable_id'   => $this->test_character->character_id,
                'affiliatable_type' => CharacterInfo::class,
                'type'              => 'forbidden',
            ],
            [
                'affiliatable_id'   => $this->secondary_character->character_id,
                'affiliatable_type' => CharacterInfo::class,
                'type'              => 'forbidden',
            ],
        ]);

        $this->assertFalse(in_array($this->test_character->character_id, $this->role->affiliated_ids));
        $this->assertFalse(in_array($this->secondary_character->character_id, $this->role->affiliated_ids));
        $this->assertFalse(in_array($this->tertiary_character->character_id, $this->role->affiliated_ids));
    }

    //TODO: Assertion that checks combination of forbidden character and allowed/inverse corporation

    // Corporation

    /** @test */
    public function characterIsInCorporationAllowedAffiliationTest()
    {
        $this->role->affiliations()->create([
            'affiliatable_id'   => $this->test_character->corporation_id,
            'affiliatable_type' => CorporationInfo::class,
            'type'              => 'allowed',
        ]);

        $this->assertTrue(in_array($this->test_character->character_id, $this->role->affiliated_ids));
        $this->assertFalse(in_array($this->secondary_character->character_id, $this->role->affiliated_ids));
        $this->assertFalse(in_array($this->tertiary_character->character_id, $this->role->affiliated_ids));
    }

    /** @test */
    public function characterIsInCorporationInversedAffiliationTest()
    {
        $this->role->affiliations()->createMany([
            [
                'affiliatable_id'   => $this->test_character->corporation_id,
                'affiliatable_type' => CorporationInfo::class,
                'type'              => 'inverse',
            ],
            [
                'affiliatable_id'   => $this->secondary_character->corporation_id,
                'affiliatable_type' => CorporationInfo::class,
                'type'              => 'inverse',
            ],
        ]);

        $this->assertFalse(in_array($this->test_character->character_id, $this->role->affiliated_ids));
        $this->assertFalse(in_array($this->secondary_character->character_id, $this->role->affiliated_ids));
        $this->assertTrue(in_array($this->tertiary_character->character_id, $this->role->affiliated_ids));
    }

    /** @test */
    public function characterIsInCorporationForbiddenAffiliationTest()
    {
        $this->role->affiliations()->createMany([
            [
                'affiliatable_id'   => $this->test_character->corporation_id,
                'affiliatable_type' => CorporationInfo::class,
                'type'              => 'forbidden',
            ],
            [
                'affiliatable_id'   => $this->secondary_character->corporation_id,
                'affiliatable_type' => CorporationInfo::class,
                'type'              => 'forbidden',
            ],
        ]);

        $this->assertFalse(in_array($this->test_character->character_id, $this->role->affiliated_ids));
        $this->assertFalse(in_array($this->secondary_character->character_id, $this->role->affiliated_ids));
        $this->assertFalse(in_array($this->tertiary_character->character_id, $this->role->affiliated_ids));
    }

    // Alliance

    /** @test */
    public function characterIsInAllianceAllowedAffiliationTest()
    {
        $this->role->affiliations()->createMany([
            [
                'affiliatable_id'   => $this->test_character->alliance_id,
                'affiliatable_type' => AllianceInfo::class,
                'type'              => 'allowed',
            ],
            [
                'affiliatable_id'   => $this->secondary_character->alliance_id,
                'affiliatable_type' => AllianceInfo::class,
                'type'              => 'allowed',
            ],
        ]);

        $this->assertTrue(in_array($this->test_character->character_id, $this->role->affiliated_ids));
        $this->assertTrue(in_array($this->secondary_character->character_id, $this->role->affiliated_ids));
        $this->assertFalse(in_array($this->tertiary_character->character_id, $this->role->affiliated_ids));
    }

    /** @test */
    public function characterIsInAllianceInversedAffiliationTest()
    {
        $this->role->affiliations()->createMany([
            [
                'affiliatable_id'   => $this->test_character->alliance_id,
                'affiliatable_type' => AllianceInfo::class,
                'type'              => 'inverse',
            ],
            [
                'affiliatable_id'   => $this->secondary_character->alliance_id,
                'affiliatable_type' => AllianceInfo::class,
                'type'              => 'inverse',
            ],
        ]);

        $this->assertFalse(in_array($this->test_character->character_id, $this->role->affiliated_ids));
        $this->assertFalse(in_array($this->secondary_character->character_id, $this->role->affiliated_ids));
        $this->assertTrue(in_array($this->tertiary_character->character_id, $this->role->affiliated_ids));
    }

    /** @test */
    public function characterIsInAllianceForbiddenAffiliationTest()
    {
        $this->role->affiliations()->createMany([
            [
                'affiliatable_id'   => $this->test_character->alliance_id,
                'affiliatable_type' => AllianceInfo::class,
                'type'              => 'forbidden',
            ],
            [
                'affiliatable_id'   => $this->secondary_character->alliance_id,
                'affiliatable_type' => AllianceInfo::class,
                'type'              => 'forbidden',
            ],
        ]);

        $this->assertFalse(in_array($this->test_character->character_id, $this->role->affiliated_ids));
        $this->assertFalse(in_array($this->secondary_character->character_id, $this->role->affiliated_ids));
        $this->assertFalse(in_array($this->tertiary_character->character_id, $this->role->affiliated_ids));
    }
}
