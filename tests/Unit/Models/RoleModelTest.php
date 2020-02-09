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
            'role_id' => $role->id,
        ]);

        $role->delete();

        $this->assertDatabaseMissing('affiliations', [
            'role_id' => $role->id,
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
            'role_id'       => $role->id,
            'permission_id' => $permission->id,
        ]);

        $role->delete();

        $this->assertDatabaseMissing('role_has_permissions', [
            'role_id'       => $role->id,
            'permission_id' => $permission->id,
        ]);
    }
}
