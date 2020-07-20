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

use Seatplus\Auth\Models\Permissions\Affiliation;
use Seatplus\Auth\Models\Permissions\Permission;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Tests\TestCase;
use Seatplus\Eveapi\Models\Character\CharacterInfo;
use Seatplus\Eveapi\Models\Corporation\CorporationInfo;

class RoleModelTest extends TestCase
{
    /**
     * @var \Seatplus\Auth\Models\Permissions\Role
     */
    private Role $role;

    public function setUp(): void
    {

        parent::setUp();

        $this->role = Role::create(['name' => 'derp']);
    }

    /** @test */
    public function it_deletes_affiliation_after_model_deletion()
    {


        $affiliation = Affiliation::create([
            'role_id' => $this->role->id,
            'affiliatable_id' => $this->test_character->corporation_id,
            'affiliatable_type' => CorporationInfo::class,
            'type' => 'allowed'
        ]);

        $this->assertDatabaseHas('affiliations', [
            'role_id' => $this->role->id,
        ]);

        $this->role->delete();

        $this->assertDatabaseMissing('affiliations', [
            'role_id' => $this->role->id,
        ]);
    }

    /** @test */
    public function it_deletes_permission_pivot_after_model_deletion()
    {

        $permission_name = 'test permission';

        $permission = Permission::create(['name' => $permission_name]);

        $this->role->givePermissionTo($permission_name);

        $this->assertDatabaseHas('role_has_permissions', [
            'role_id'       => $this->role->id,
            'permission_id' => $permission->id,
        ]);

        $this->role->delete();

        $this->assertDatabaseMissing('role_has_permissions', [
            'role_id'       => $this->role->id,
            'permission_id' => $permission->id,
        ]);
    }

    /** @test */
    public function it_has_polymorphic_relation()
    {

        $affiliation = Affiliation::create([
            'role_id' => $this->role->id,
            'affiliatable_id' => $this->test_character->corporation_id,
            'affiliatable_type' => CorporationInfo::class,
            'type' => 'allowed'
        ]);

        $this->assertEquals(CorporationInfo::class, get_class($this->role->affiliations->first()->affiliatable));

    }

    /** @test */
    public function it_has_default_type_attribute()
    {

        $this->assertEquals('manual', $this->role->fresh()->type);
    }

    /** @test */
    public function it_has_acl_affiliations()
    {

        $this->role->acl_affiliations()->create([
            'affiliatable_id' => $this->test_character->character_id,
            'affiliatable_type' => CharacterInfo::class,
        ]);

        $this->assertEquals(CharacterInfo::class, get_class($this->role->acl_affiliations->first()->affiliatable));
    }

    /** @test */
    public function it_has_acl_moderators()
    {

        $this->role->acl_affiliations()->create([
            'affiliatable_id' => $this->test_character->character_id,
            'affiliatable_type' => CharacterInfo::class,
            'can_moderate' => true
        ]);

        $this->assertTrue($this->role->acl_affiliations->isEmpty());

        $this->assertEquals(CharacterInfo::class, get_class($this->role->moderators->first()->affiliatable));
    }

    /** @test */
    public function it_has_acl_members()
    {

        $this->role->members()->create([
            'user_id' => $this->test_user->id,
            'status' => 'member'
        ]);

        $this->assertTrue($this->role->members->isNotEmpty());
    }

    /** @test */
    public function one_can_add_member()
    {
        $this->role->activateMember($this->test_user);

        $this->assertTrue($this->role->members->isNotEmpty());
    }

    /** @test */
    public function one_can_pause_member()
    {
        $this->role->activateMember($this->test_user);

        $this->assertTrue($this->role->members->isNotEmpty());

        $this->role->pauseMember($this->test_user);

        $this->assertTrue($this->role->refresh()->members->isEmpty());
    }

    /** @test */
    public function one_can_remove_member()
    {
        $this->role->activateMember($this->test_user);

        $this->assertTrue($this->role->members->isNotEmpty());

        $this->role->removeMember($this->test_user);

        $this->assertTrue($this->role->refresh()->members->isEmpty());
    }

    /** @test */
    public function it_throws_error_if_unaffiliated_user_wants_to_join()
    {
        $role = Role::create(['name' => 'test', 'type' => 'on-request']);

        $this->expectExceptionMessage('User is not allowed for this access control group');

        $role->activateMember($this->test_user);
    }

    /** @test */
    public function it_throws_error_if_one_tries_to_join_waitlist_on_invalid_role()
    {

        $this->expectExceptionMessage('Only on-request control groups do have a waitlist');

        $this->role->joinWaitlist($this->test_user);
    }

    /** @test */
    public function it_throws_error_if_unaffiliated_user_tries_to_join_waitlist()
    {

        $role = Role::create(['name' => 'test', 'type' => 'on-request']);

        $this->expectExceptionMessage('User is not allowed for this access control group');

        $role->joinWaitlist($this->test_user);
    }

    /** @test */
    public function user_can_join_waitlist()
    {

        $role = Role::create(['name' => 'test', 'type' => 'on-request']);

        $role->acl_affiliations()->create([
            'affiliatable_id' => $this->test_character->character_id,
            'affiliatable_type' => CharacterInfo::class
        ]);

        $role->joinWaitlist($this->test_user);

        $this->assertEquals($this->test_user->id, $role->refresh()->acl_members()->whereStatus('waitlist')->first()->user_id);
    }

    /** @test */
    public function one_can_get_moderator_ids()
    {

        $role = Role::create(['name' => 'test', 'type' => 'on-request']);

        $role->acl_affiliations()->create([
            'affiliatable_id' => $this->test_character->character_id,
            'affiliatable_type' => CharacterInfo::class,
            'can_moderate' => true
        ]);

        $this->assertTrue($role->refresh()->isModerator($this->test_user));
    }
}
