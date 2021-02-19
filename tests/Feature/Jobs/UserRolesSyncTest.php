<?php


namespace Seatplus\Auth\Tests\Feature\Jobs;


use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Seatplus\Auth\Jobs\DispatchUserRoleSync;
use Seatplus\Auth\Jobs\UserRolesSync;
use Seatplus\Auth\Models\AccessControl\AclMember;
use Seatplus\Auth\Models\Permissions\Affiliation;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Models\User;
use Seatplus\Auth\Tests\TestCase;
use Seatplus\Eveapi\Models\Character\CharacterInfo;
use Seatplus\Eveapi\Models\RefreshToken;

class UserRolesSyncTest extends TestCase
{
    /**
     * @var \Illuminate\Database\Eloquent\Builder|\Illuminate\Database\Eloquent\Model
     */
    private Role $role;

    /**
     * @var \Seatplus\Auth\Jobs\UserRolesSync
     */
    private UserRolesSync $job;

    public function setUp(): void
    {

        parent::setUp();

        $this->role = Role::create(['name' => 'derp']);

        $this->test_user = $this->test_user->refresh();

        $this->job = new UserRolesSync($this->test_user);
    }

    /** @test */
    public function it_gives_automatic_role()
    {
        // Update role to be automatic
        $this->role->update(['type' => 'automatic']);

        //assure that role is of type auto
        $this->assertEquals('automatic', $this->role->type);

        // First create acl affiliation with user
        $this->role->acl_affiliations()->create([
            'affiliatable_id' => $this->test_character->character_id,
            'affiliatable_type' => CharacterInfo::class,
        ]);

        $this->assertTrue($this->role->members->isEmpty());

        $this->job->handle();

        $this->assertFalse($this->role->refresh()->members->isEmpty());

        $this->assertTrue($this->test_user->hasRole('derp'));
    }

    /** @test */
    public function it_removes_automatic_role()
    {

        $this->it_gives_automatic_role();

        RefreshToken::find($this->test_character->character_id)->delete();

        // we need a new job instance, as the valid character_ids are build in the constructor of the job
        $job = new UserRolesSync($this->test_user->refresh());
        $job->handle();

        $this->assertFalse($this->test_user->hasRole('derp'));

    }

    /** @test */
    public function it_adds_membership_for_paused_user()
    {
        // Update role to be on-request
        $this->role->update(['type' => 'on-request']);

        //assure that role is of type auto
        $this->assertEquals('on-request', $this->role->type);

        // First create acl affiliation with user
        $this->role->acl_affiliations()->create([
            'affiliatable_id' => $this->test_character->character_id,
            'affiliatable_type' => CharacterInfo::class,
        ]);

        // Second add character as paused to role
        $this->role->acl_members()->create([
            'user_id' => $this->test_user->getAuthIdentifier(),
            'status' => 'paused'
        ]);

        $this->assertTrue($this->role->members->isEmpty());

        $this->job->handle();

        $this->assertFalse($this->role->refresh()->members->isEmpty());
    }

    /** @test */
    public function it_removes_membership_if_refresh_token_is_removed()
    {
        // Update role to be on-request
        $this->role->update(['type' => 'on-request']);

        //assure that role is of type auto
        $this->assertEquals('on-request', $this->role->type);

        // First create acl affiliation with user
        $this->role->acl_affiliations()->create([
            'affiliatable_id' => $this->test_character->character_id,
            'affiliatable_type' => CharacterInfo::class,
        ]);

        // Second add character as paused to role
        $this->role->acl_members()->create([
            'user_id' => $this->test_user->getAuthIdentifier(),
            'status' => 'member'
        ]);

        $this->assertFalse($this->role->refresh()->members->isEmpty());

        // Remove refresh_token
        RefreshToken::find($this->test_character->character_id)->delete();

        // we need a new job instance, as the valid character_ids are build in the constructor of the job
        $job = new UserRolesSync($this->test_user->refresh());
        $job->handle();

        $this->assertTrue($this->role->refresh()->members->isEmpty());
    }

    /** @test */
    public function roles_without_acl_affiliations_are_not_impacted_by_job()
    {
        // Update role to be on-request
        $this->role->update(['type' => 'automatic']);

        $this->assertTrue($this->role->acl_affiliations->isEmpty());

        //assure that role is of type auto
        $this->assertEquals('automatic', $this->role->type);


        $this->assertFalse($this->test_user->hasRole($this->role));

        $this->job->handle();

        $this->assertFalse($this->test_user->hasRole($this->role));
    }

    /** @test */
    public function dispatching_roles_sync()
    {
        Queue::fake();

        $dispatch_job = new DispatchUserRoleSync;

        $dispatch_job->handle();

        Queue::assertPushedOn('high', UserRolesSync::class);
    }

}
