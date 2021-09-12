<?php


use Seatplus\Auth\Models\AccessControl\AclMember;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Models\User;
use Seatplus\Auth\Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    test()->role = Role::create(['name' => 'derp']);
});

it('has user relationship', function () {
    test()->role->members()->create([
        'user_id' => test()->test_user->id,
        'status' => 'member'
    ]);

    $member = AclMember::where('user_id',test()->test_user->id)
        ->get()->first();

    expect($member->user::class)->toEqual(User::class);

});
