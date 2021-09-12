<?php


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

uses(TestCase::class);

beforeEach(function () {
    test()->role = Role::create(['name' => 'derp']);

    test()->test_user = test()->test_user->refresh();

    test()->job = new UserRolesSync(test()->test_user);
});

it('gives automatic role', function () {
    // Update role to be automatic
    test()->role->update(['type' => 'automatic']);

    //assure that role is of type auto
    test()->assertEquals('automatic', test()->role->type);

    // First create acl affiliation with user
    test()->role->acl_affiliations()->create([
        'affiliatable_id' => test()->test_character->character_id,
        'affiliatable_type' => CharacterInfo::class,
    ]);

    test()->assertTrue(test()->role->members->isEmpty());

    test()->job->handle();

    test()->assertFalse(test()->role->refresh()->members->isEmpty());

    test()->assertTrue(test()->test_user->hasRole('derp'));
});

it('removes automatic role', function () {

    test()->it_gives_automatic_role();

    RefreshToken::find(test()->test_character->character_id)->delete();

    // we need a new job instance, as the valid character_ids are build in the constructor of the job
    $job = new UserRolesSync(test()->test_user->refresh());
    $job->handle();

    test()->assertFalse(test()->test_user->hasRole('derp'));

});

it('adds membership for paused user', function () {
    // Update role to be on-request
    test()->role->update(['type' => 'on-request']);

    //assure that role is of type auto
    test()->assertEquals('on-request', test()->role->type);

    // First create acl affiliation with user
    test()->role->acl_affiliations()->create([
        'affiliatable_id' => test()->test_character->character_id,
        'affiliatable_type' => CharacterInfo::class,
    ]);

    // Second add character as paused to role
    test()->role->acl_members()->create([
        'user_id' => test()->test_user->getAuthIdentifier(),
        'status' => 'paused'
    ]);

    test()->assertTrue(test()->role->members->isEmpty());

    test()->job->handle();

    test()->assertFalse(test()->role->refresh()->members->isEmpty());
});

it('removes membership if refresh token is removed', function () {
    // Update role to be on-request
    test()->role->update(['type' => 'on-request']);

    //assure that role is of type auto
    test()->assertEquals('on-request', test()->role->type);

    // First create acl affiliation with user
    test()->role->acl_affiliations()->create([
        'affiliatable_id' => test()->test_character->character_id,
        'affiliatable_type' => CharacterInfo::class,
    ]);

    // Second add character as paused to role
    test()->role->acl_members()->create([
        'user_id' => test()->test_user->getAuthIdentifier(),
        'status' => 'member'
    ]);

    test()->assertFalse(test()->role->refresh()->members->isEmpty());

    // Remove refresh_token
    RefreshToken::find(test()->test_character->character_id)->delete();

    // we need a new job instance, as the valid character_ids are build in the constructor of the job
    $job = new UserRolesSync(test()->test_user->refresh());
    $job->handle();

    test()->assertTrue(test()->role->refresh()->members->isEmpty());
});

test('roles without acl affiliations are not impacted by job', function () {
    // Update role to be on-request
    test()->role->update(['type' => 'automatic']);

    test()->assertTrue(test()->role->acl_affiliations->isEmpty());

    //assure that role is of type auto
    test()->assertEquals('automatic', test()->role->type);


    test()->assertFalse(test()->test_user->hasRole(test()->role));

    test()->job->handle();

    test()->assertFalse(test()->test_user->hasRole(test()->role));
});

test('dispatching roles sync', function () {
    Queue::fake();

    $dispatch_job = new DispatchUserRoleSync;

    $dispatch_job->handle();

    Queue::assertPushedOn('high', UserRolesSync::class);
});
