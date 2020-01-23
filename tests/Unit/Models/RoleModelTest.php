<?php


namespace Seatplus\Auth\Tests\Unit\Models;


use Seatplus\Auth\Models\Permissions\Permission;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Tests\TestCase;

class RoleModelTest extends TestCase
{
    /** @test */
    public function it_deletes_affiliation_after_model_deletion()
    {
        $role = Role::create(['name' => 'derp']);

        $role->affiliations()->create([
            'corporation_id' => $this->test_character->corporation_id,
            'type'           => 'allowed',
        ]);

        $this->assertDatabaseHas('affiliations', [
            'role_id' => $role->id
        ]);

        $role->delete();

        $this->assertDatabaseMissing('affiliations', [
            'role_id' => $role->id
        ]);
    }

    /** @test */
    public function it_deletes_permission_pivot_after_model_deletion()
    {
        $role = Role::create(['name' => 'derp']);

        $permission_name = 'test permission';

        $permission = Permission::create(['name' => $permission_name]);

        $role->givePermissionTo($permission_name);

        $this->assertDatabaseHas('role_has_permissions', [
            'role_id' => $role->id,
            'permission_id' => $permission->id
        ]);

        $role->delete();

        $this->assertDatabaseMissing('role_has_permissions', [
            'role_id' => $role->id,
            'permission_id' => $permission->id
        ]);
    }

}
