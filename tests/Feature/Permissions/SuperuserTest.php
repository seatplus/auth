<?php


namespace Seatplus\Auth\Tests\Feature\Permissions;


use Seatplus\Auth\Models\Permissions\Permission;
use Seatplus\Auth\Tests\TestCase;

class SuperuserTest extends TestCase
{
    /** @test */
    public function superuser_got_any_permission()
    {
        $superuser_permission = Permission::create(['name' => 'superuser']);

        $this->test_user->givePermissionTo('superuser');

        $another_permission = Permission::create(['name' => 'another permission']);

        $this->assertTrue($this->test_user->can($another_permission->name));
    }

}
