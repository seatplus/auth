<?php

use Seatplus\Auth\Jobs\RoleMemberSync;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Services\Roles\BaseRoleService;

beforeEach(function () {
    $this->serviceMock = mock(BaseRoleService::class);
    $this->job = new RoleMemberSync($this->serviceMock);
});

afterEach(function () {
    Mockery::close();
});

it('handles role member synchronization successfully', function () {

    $role = Role::create(['name' => 'derp']);

    $this->serviceMock->shouldReceive('for')->once()->andReturnSelf();
    $this->serviceMock->shouldReceive('handleMembers')->once();

    $this->job->handle();
});

it('returns correct tags for the job', function () {
    $tags = $this->job->tags();

    expect($tags)->toBe(['Dispatch Role Updates']);
});
