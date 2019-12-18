<?php


namespace Seatplus\Auth\Tests\Unit\Actions;

use Seatplus\Auth\Actions\GetAffiliatedCharactersIdsByPermissionArray;
use Seatplus\Auth\Models\Permissions\Permission;
use Seatplus\Auth\Models\Permissions\Role;
use Seatplus\Auth\Tests\TestCase;
use Seatplus\Eveapi\Models\Character\CharacterInfo;

class GetAffiliatedCharacterIdsByPermissionArrayTest extends TestCase
{

    protected $role;

    protected $permission;

    protected $test_character_user;

    private $test_character;

    private $secondary_character;

    private $tertiary_character;


    protected function setUp(): void
    {
        parent::setUp();

        $this->role = Role::create(['name' => 'writer']);
        $this->permission = Permission::create(['name' => 'edit articles']);

        $this->role->givePermissionTo($this->permission);
        $this->test_user->assignRole($this->role);

        $this->test_character_user = $this->test_user->characters->first();

        $this->actingAs($this->test_user);

        $this->test_character = factory(CharacterInfo::class)->create([
            'character_id' => $this->test_character_user->character_id
        ]);

        $this->secondary_character = factory(CharacterInfo::class)->create();

        $this->tertiary_character = factory(CharacterInfo::class)->create();
    }

    /**
     * @test
     * @throws \Exception
     */
    public function it_returns_own_character_id()
    {

        $this->role->affiliations()->create([
            'allowed' => collect([
                'character_ids' => [12345],
            ])
        ]);

        $ids = (new GetAffiliatedCharactersIdsByPermissionArray($this->permission->name))->execute();

        $this->assertTrue(in_array($this->test_character_user->character_id, $ids));
    }

    /** @test */
    public function it_returns_other_and_own_character_id_for_inverted()
    {

        $this->role->affiliations()->create([
            'inverse' => collect([
                'corporation_ids' => [$this->test_character->corporation->corporation_id]
            ])
        ]);

        $ids = (new GetAffiliatedCharactersIdsByPermissionArray($this->permission->name))->execute();

        $this->assertTrue(in_array($this->test_character_user->character_id, $ids));
        $this->assertTrue(in_array($this->secondary_character->character_id, $ids));
    }

    /** @test */
    public function it_does_not_return_secondary_character_id_if_secondary_character_is_inverted()
    {

        $this->role->affiliations()->create([
            'inverse' => collect([
                'character_ids' => [$this->secondary_character->character_id]
            ])
        ]);

        $ids = (new GetAffiliatedCharactersIdsByPermissionArray($this->permission->name))->execute();

        // Assert that the owned character_ids are present
        $this->assertTrue(in_array($this->test_character_user->character_id, $ids));

        // Assert that ids from the inverted corporation is missing
        $this->assertFalse(in_array($this->secondary_character->character_id, $ids));

        // Assert that ids from any other third party is present
        $this->assertTrue(in_array($this->tertiary_character->character_id, $ids));

    }

    /** @test */
    public function it_does_not_return_secondary_character_id_if_secondary_corporation_is_inverted()
    {

        $this->role->affiliations()->create([
            'inverse' => collect([
                'corporation_ids' => [$this->secondary_character->corporation->corporation_id]
            ])
        ]);

        $ids = (new GetAffiliatedCharactersIdsByPermissionArray($this->permission->name))->execute();

        // Assert that second character does not share same corporation as the first character
        $this->assertNotEquals($this->secondary_character->corporation->corporation_id, $this->test_character->corporation->corporation_id);

        // Assert that the owned character_ids are present
        $this->assertTrue(in_array($this->test_character_user->character_id, $ids));

        // Assert that ids from the inverted corporation is missing
        $this->assertFalse(in_array($this->secondary_character->character_id, $ids));

        // Assert that ids from any other third party is present
        $this->assertTrue(in_array($this->tertiary_character->character_id, $ids));

    }

    /** @test */
    public function it_does_not_return_secondary_character_id_if_secondary_alliance_is_inverted()
    {

        $this->role->affiliations()->create([
            'inverse' => collect([
                'alliance_ids' => [$this->secondary_character->alliance->alliance_id]
            ])
        ]);

        $ids = (new GetAffiliatedCharactersIdsByPermissionArray($this->permission->name))->execute();

        // Assert that second character does not share same corporation as the first character
        $this->assertNotEquals($this->secondary_character->alliance->alliance_id, $this->test_character->alliance->alliance_id);

        // Assert that the owned character_ids are present
        $this->assertTrue(in_array($this->test_character_user->character_id, $ids));

        // Assert that ids from the inverted corporation is missing
        $this->assertFalse(in_array($this->secondary_character->character_id, $ids));

        // Assert that ids from any other third party is present
        $this->assertTrue(in_array($this->tertiary_character->character_id, $ids));

    }

    /** @test */
    public function it_does_return_secondary_character_id_if_secondary_character_is_allowed()
    {

        $this->role->affiliations()->create([
            'allowed' => collect([
                'character_ids' => [$this->secondary_character->character_id]
            ])
        ]);

        $ids = (new GetAffiliatedCharactersIdsByPermissionArray($this->permission->name))->execute();

        // Assert that second character does not share same corporation as the first character
        $this->assertNotEquals($this->secondary_character->character_id, $this->test_character->character_id);

        // Assert that the owned character_ids are present
        $this->assertTrue(in_array($this->test_character_user->character_id, $ids));

        // Assert that ids from the allowed character is present
        $this->assertTrue(in_array($this->secondary_character->character_id, $ids));

        // Assert that ids from any other third party is not present
        $this->assertFalse(in_array($this->tertiary_character->character_id, $ids));

    }

    /** @test */
    public function it_does_return_secondary_character_id_if_secondary_corporation_is_allowed()
    {

        $this->role->affiliations()->create([
            'allowed' => collect([
                'corporation_ids' => [$this->secondary_character->corporation->corporation_id]
            ])
        ]);

        $ids = (new GetAffiliatedCharactersIdsByPermissionArray($this->permission->name))->execute();

        // Assert that second character does not share same corporation as the first character
        $this->assertNotEquals($this->secondary_character->corporation->corporation_id, $this->test_character->corporation->corporation_id);

        // Assert that the owned character_ids are present
        $this->assertTrue(in_array($this->test_character_user->character_id, $ids));

        // Assert that ids from the allowed character is present
        $this->assertTrue(in_array($this->secondary_character->character_id, $ids));

        // Assert that ids from any other third party is not present
        $this->assertFalse(in_array($this->tertiary_character->character_id, $ids));
    }

    /** @test */
    public function it_does_return_secondary_character_id_if_secondary_alliance_is_allowed()
    {

        $this->role->affiliations()->create([
            'allowed' => collect([
                'alliance_ids' => [$this->secondary_character->alliance->alliance_id]
            ])
        ]);

        $ids = (new GetAffiliatedCharactersIdsByPermissionArray($this->permission->name))->execute();

        // Assert that second character does not share same corporation as the first character
        $this->assertNotEquals($this->secondary_character->alliance->alliance_id, $this->test_character->alliance->alliance_id);

        // Assert that the owned character_ids are present
        $this->assertTrue(in_array($this->test_character_user->character_id, $ids));

        // Assert that ids from the allowed character is present
        $this->assertTrue(in_array($this->secondary_character->character_id, $ids));

        // Assert that ids from any other third party is not present
        $this->assertFalse(in_array($this->tertiary_character->character_id, $ids));
    }

    /** @test */
    public function it_does_return_own_character_even_if_listed_as_forbidden()
    {

        $this->role->affiliations()->create([
            'forbidden' => collect([
                'character_ids' => [$this->test_character_user->character_id]
            ])
        ]);

        $ids = (new GetAffiliatedCharactersIdsByPermissionArray($this->permission->name))->execute();

        // Assert that the owned character_ids are present
        $this->assertTrue(in_array($this->test_character_user->character_id, $ids));
    }

    /** @test */
    public function it_does_not_return_secondary_character_id_if_secondary_character_is_forbidden()
    {

        $this->role->affiliations()->create([
            'allowed' => collect([
                'character_ids' => [$this->secondary_character->character_id]
            ]),
            'forbidden' => collect([
                'character_ids' => [$this->secondary_character->character_id]
            ])
        ]);

        $ids = (new GetAffiliatedCharactersIdsByPermissionArray($this->permission->name))->execute();

        // Assert that second character does not share same corporation as the first character
        $this->assertNotEquals($this->secondary_character->character_id, $this->test_character->character_id);

        // Assert that the owned character_ids are present
        $this->assertTrue(in_array($this->test_character_user->character_id, $ids));

        // Assert that ids from the allowed character is not present
        $this->assertFalse(in_array($this->secondary_character->character_id, $ids));

        // Assert that ids from any other third party is not present
        $this->assertFalse(in_array($this->tertiary_character->character_id, $ids));
    }

    /** @test */
    public function it_does_not_return_secondary_character_id_if_secondary_corporation_is_forbidden()
    {
        $this->role->affiliations()->create([
        'allowed' => collect([
            'corporation_ids' => [$this->secondary_character->corporation->corporation_id]
        ]),
        'forbidden' => collect([
            'corporation_ids' => [$this->secondary_character->corporation->corporation_id]
        ])
    ]);

        $ids = (new GetAffiliatedCharactersIdsByPermissionArray($this->permission->name))->execute();

        // Assert that second character does not share same corporation as the first character
        $this->assertNotEquals($this->secondary_character->corporation->corporation_id, $this->test_character->corporation->corporation_id);

        // Assert that the owned character_ids are present
        $this->assertTrue(in_array($this->test_character_user->character_id, $ids));

        // Assert that ids from the allowed character is not present
        $this->assertFalse(in_array($this->secondary_character->character_id, $ids));

        // Assert that ids from any other third party is not present
        $this->assertFalse(in_array($this->tertiary_character->character_id, $ids));
    }

    /** @test */
    public function it_does_not_return_secondary_character_id_if_secondary_alliance_is_forbidden()
    {
        $this->role->affiliations()->create([
            'allowed' => collect([
                'alliance_ids' => [$this->secondary_character->alliance->alliance_id]
            ]),
            'forbidden' => collect([
                'alliance_ids' => [$this->secondary_character->alliance->alliance_id]
            ])
        ]);

        $ids = (new GetAffiliatedCharactersIdsByPermissionArray($this->permission->name))->execute();

        // Assert that second character does not share same corporation as the first character
        $this->assertNotEquals($this->secondary_character->alliance->alliance_id, $this->test_character->alliance->alliance_id);

        // Assert that the owned character_ids are present
        $this->assertTrue(in_array($this->test_character_user->character_id, $ids));

        // Assert that ids from the allowed character is not present
        $this->assertFalse(in_array($this->secondary_character->character_id, $ids));

        // Assert that ids from any other third party is not present
        $this->assertFalse(in_array($this->tertiary_character->character_id, $ids));
    }

    /** @test */
    public function it_does_work_with_model_method()
    {
        $this->role->affiliations()->create([
            'allowed' => collect([
                'alliance_ids' => [$this->secondary_character->alliance->alliance_id]
            ]),
            'forbidden' => collect([
                'alliance_ids' => [$this->secondary_character->alliance->alliance_id]
            ])
        ]);

        $ids = $this->test_user->getAffiliatedCharacterIdsByPermission($this->permission);

        // Assert that second character does not share same corporation as the first character
        $this->assertNotEquals($this->secondary_character->alliance->alliance_id, $this->test_character->alliance->alliance_id);

        // Assert that the owned character_ids are present
        $this->assertTrue(in_array($this->test_character_user->character_id, $ids));

        // Assert that ids from the allowed character is not present
        $this->assertFalse(in_array($this->secondary_character->character_id, $ids));

        // Assert that ids from any other third party is not present
        $this->assertFalse(in_array($this->tertiary_character->character_id, $ids));
    }

}
