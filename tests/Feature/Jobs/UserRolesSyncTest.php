<?php


use Illuminate\Support\Facades\Queue;
use Seatplus\Auth\Jobs\DispatchUserRoleSync;
use Seatplus\Auth\Jobs\UserRolesSync;
use Seatplus\Auth\Models\Permissions\Affiliation;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Models\User;
use Seatplus\Eveapi\Models\Character\CharacterInfo;
use Seatplus\Eveapi\Models\RefreshToken;

beforeEach(function () {
    test()->role = Role::create(['name' => 'derp']);

    test()->test_user = test()->test_user->refresh();

    test()->job = new UserRolesSync(test()->test_user);
});

it('gives automatic role', function () {
    // Update role to be automatic
    test()->role->update(['type' => 'automatic']);

    //assure that role is of type auto
    expect(test()->role->type)->toEqual('automatic');

    // First create acl affiliation with user
    test()->role->acl_affiliations()->create([
        'affiliatable_id' => test()->test_character->character_id,
        'affiliatable_type' => CharacterInfo::class,
    ]);

    expect(test()->role->members->isEmpty())->toBeTrue();

    test()->job->handle();

    expect(test()->role->refresh()->members->isEmpty())->toBeFalse();

    expect(test()->test_user->hasRole('derp'))->toBeTrue();
});

it('removes automatic role', function () {

    // Update role to be automatic
    test()->role->update(['type' => 'automatic']);

    //assure that role is of type auto
    expect(test()->role->type)->toEqual('automatic');

    // First create acl affiliation with user
    test()->role->acl_affiliations()->create([
        'affiliatable_id' => test()->test_character->character_id,
        'affiliatable_type' => CharacterInfo::class,
    ]);

    expect(test()->role->members->isEmpty())->toBeTrue();

    test()->job->handle();

    expect(test()->role->refresh()->members->isEmpty())->toBeFalse();

    expect(test()->test_user->hasRole('derp'))->toBeTrue();

    RefreshToken::find(test()->test_character->character_id)->delete();

    // we need a new job instance, as the valid character_ids are build in the constructor of the job
    $job = new UserRolesSync(test()->test_user->refresh());
    $job->handle();

    expect(test()->test_user->hasRole('derp'))->toBeFalse();

});

it('adds membership for paused user', function () {
    // Update role to be on-request
    test()->role->update(['type' => 'on-request']);

    //assure that role is of type auto
    expect(test()->role->type)->toEqual('on-request');

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

    expect(test()->role->members->isEmpty())->toBeTrue();

    test()->job->handle();

    expect(test()->role->refresh()->members->isEmpty())->toBeFalse();
});

it('removes membership if refresh token is removed', function () {
    // Update role to be on-request
    test()->role->update(['type' => 'on-request']);

    //assure that role is of type auto
    expect(test()->role->type)->toEqual('on-request');

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

    expect(test()->role->refresh()->members->isEmpty())->toBeFalse();

    // Remove refresh_token
    RefreshToken::find(test()->test_character->character_id)->delete();

    // we need a new job instance, as the valid character_ids are build in the constructor of the job
    $job = new UserRolesSync(test()->test_user->refresh());
    $job->handle();

    expect(test()->role->refresh()->members->isEmpty())->toBeTrue();
});

test('roles without acl affiliations are not impacted by job', function () {
    // Update role to be on-request
    test()->role->update(['type' => 'automatic']);

    expect(test()->role->acl_affiliations->isEmpty())->toBeTrue();

    //assure that role is of type auto
    expect(test()->role->type)->toEqual('automatic');


    expect(test()->test_user->hasRole(test()->role))->toBeFalse();

    test()->job->handle();

    expect(test()->test_user->hasRole(test()->role))->toBeFalse();
});

test('dispatching roles sync', function () {
    Queue::fake();

    $dispatch_job = new DispatchUserRoleSync;

    $dispatch_job->handle();

    Queue::assertPushedOn('high', UserRolesSync::class);
});
