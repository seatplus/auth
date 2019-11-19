<?php

namespace Seatplus\Web\Tests\Unit\Traits;

use Seatplus\Auth\Models\Permissions\Permission;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Tests\TestCase;

class AccessCheckerTraitTest extends TestCase
{
    protected $role;

    protected $permission;

    protected $test_character;

    protected function setUp(): void
    {

        parent::setUp();

        $this->role = Role::create(['name' => 'writer']);
        $this->permission = Permission::create(['name' => 'edit articles']);

        $this->role->givePermissionTo($this->permission);
        $this->test_user->assignRole($this->role);

        $this->test_character = $this->test_user->characters->first();

    }

    /** @test */
    public function haveAccessToCharacterTest()
    {
        $this->role->affiliations()->create([
            'allowed' => collect([
                'character_ids' => [12345]
            ])
        ]);


        $this->assertTrue($this->test_user->hasAccessTo($this->permission->name, 12345));
    }

    /** @test */
    public function haveNoAccessToCharacterTest()
    {
        $this->role->affiliations()->create([
            'allowed' => collect([
                'character_ids' => [12345]
            ])
        ]);

        $this->assertFalse($this->test_user->hasAccessTo($this->permission->name, 54321));
    }

    /** @test */
    public function haveAccessToOwnedCharacterTest()
    {
        $this->role->affiliations()->create([
            'forbidden' => collect([
                'character_ids' => [$this->test_character->character_id]
            ])
        ]);

        $this->assertTrue($this->test_user->hasAccessTo($this->permission->name, $this->test_character->character_id));
    }

    /** @test */
    public function haveAccessToInverseCharacterTest()
    {
        $this->role->affiliations()->create([
            'inverse' => collect([
                'character_ids' => [54321]
            ])
        ]);

        $this->assertTrue($this->test_user->hasAccessTo($this->permission->name, $this->test_character->character_id));
    }

    /** @test */
    public function haveNotAccessToInverseCharacterTest()
    {
        $this->role->affiliations()->create([
            'inverse' => collect([
                'character_ids' => [54321]
            ])
        ]);

        $this->assertFalse($this->test_user->hasAccessTo($this->permission->name, 54321));
    }

    /** @test */
    public function haveAccessToInverseCorporationTest()
    {
        $this->role->affiliations()->create([
            'inverse' => collect([
                'corporation_ids' => [12345]
            ])
        ]);

        $this->assertTrue($this->test_user->hasAccessTo($this->permission->name, 98547));
    }

    /** @test */
    public function haveNotAccessToInverseCorporationTest()
    {
        $this->role->affiliations()->create([
            'inverse' => collect([
                'corporation_ids' => [$this->test_character->corporation_id]
            ])
        ]);

        // asserting true because it is an owned recource and therefore one always has access to data that is gained through user
        $this->assertTrue($this->test_user->hasAccessTo($this->permission->name, $this->test_character->character_id));
    }

}
