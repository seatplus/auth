<?php

namespace Seatplus\Auth\Tests\Unit\Affiliations;

use Illuminate\Support\Arr;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Tests\TestCase;

class SeatPlusRolesTest extends TestCase
{
    /** @test */
    public function userHasNoRolesTest()
    {
        //dd('userHasNoRole',$this->test_user->characters->first()->user);

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

        $this->assertNUll($role->affiliations);
    }

    /** @test */
    public function roleHasAnAffiliationTest()
    {
        $role = Role::create(['name' => 'derp']);

        $role->affiliations()->create();

        $this->assertNotNUll($role->affiliations);
    }

    /** @test */
    public function userIsInAffiliationTest()
    {
        $role = Role::create(['name' => 'derp']);

        $role->affiliations()->create([
            'allowed' => collect([
                'character_ids' => $this->test_user->id,
            ]),
        ]);

        $this->assertTrue($role->isAffiliated($this->test_user->id));
    }

    /** @test */
    public function characterIsInCharacterAllowedAffiliationTest()
    {
        $role = Role::create(['name' => 'derp']);

        $role->affiliations()->create([
            'allowed' => collect([
                'character_ids' => [$this->test_user->id, 12345],
            ]),
        ]);

        //dd(Arr::flatten($role->affiliations->allowed));

        $this->assertTrue($role->isAffiliated($this->test_user->id));
    }

    /** @test */
    public function characterIsInCharacterInversedAffiliationTest()
    {
        $role = Role::create(['name' => 'derp']);

        $role->affiliations()->create([
            'inverse' => collect([
                'character_ids' => [$this->test_user->id, 12345],
            ]),
        ]);

        $this->assertFalse($role->isAffiliated($this->test_user->id));
    }

    /** @test */
    public function characterIsInCharacterForbiddenAffiliationTest()
    {
        $role = Role::create(['name' => 'derp']);

        $role->affiliations()->create([
            'forbidden' => collect([
                'character_ids' => [$this->test_user->id, 12345],
            ]),
        ]);

        $this->assertFalse($role->isAffiliated($this->test_user->id));
    }

    //TODO: Assertion that checks combination of forbidden character and allowed/inverse corporation

    // Corporation

    /** @test */
    public function characterIsInCorporationAllowedAffiliationTest()
    {
        $role = Role::create(['name' => 'derp']);

        $role->affiliations()->create([
            'allowed' => collect([
                'corporation_ids' => Arr::flatten([$this->test_user->characters->map(function ($char) {
                    return optional($char->character)->corporation_id;
                }), 12345]),
            ]),
        ]);

        $this->assertTrue($role->isAffiliated(12345));
    }

    /** @test */
    public function characterIsInCorporationInversedAffiliationTest()
    {
        $role = Role::create(['name' => 'derp']);

        $role->affiliations()->create([
            'inverse' => collect([
                'corporation_ids' => Arr::flatten([$this->test_user->characters->map(function ($char) {
                    return optional($char->character)->corporation_id;
                }), 12345]),
            ]),
        ]);

        $this->assertFalse($role->isAffiliated(12345));
    }

    /** @test */
    public function characterIsInCorporationForbiddenAffiliationTest()
    {
        $role = Role::create(['name' => 'derp']);

        $role->affiliations()->create([
            'forbidden' => collect([
                'corporation_ids' => Arr::flatten([$this->test_user->characters->map(function ($char) {
                    return optional($char->character)->corporation_id;
                }), 12345]),
            ]),
        ]);

        $this->assertFalse($role->isAffiliated(12345));
    }

    // Alliance

    /** @test */
    public function characterIsInAllianceAllowedAffiliationTest()
    {
        $role = Role::create(['name' => 'derp']);

        $role->affiliations()->create([
            'allowed' => collect([
                'alliance_ids' => Arr::flatten([$this->test_user->characters->map(function ($char) {
                    return optional($char->character)->alliance_id;
                }), 12345]),
            ]),
        ]);

        $this->assertTrue($role->isAffiliated(12345));
    }

    /** @test */
    public function characterIsInAllianceInversedAffiliationTest()
    {
        $role = Role::create(['name' => 'derp']);

        $role->affiliations()->create([
            'inverse' => collect([
                'alliance_ids' => Arr::flatten([$this->test_user->characters->map(function ($char) {
                    return optional($char->character)->alliance_id;
                }), 12345]),
            ]),
        ]);

        $this->assertFalse($role->isAffiliated(12345));
        $this->assertTrue($role->isAffiliated(54321));
    }

    /** @test */
    public function characterIsInAllianceForbiddenAffiliationTest()
    {
        $role = Role::create(['name' => 'derp']);

        $role->affiliations()->create([
            'forbidden' => collect([
                'alliance_ids' => Arr::flatten([$this->test_user->characters->map(function ($char) {
                    return optional($char->character)->alliance_id;
                }), 12345]),
            ]),
        ]);

        $this->assertFalse($role->isAffiliated(12345));
    }
}
