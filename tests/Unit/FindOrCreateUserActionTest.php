<?php

namespace Seatplus\Auth\Tests;

use Faker\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Two\User as SocialiteUser;
use Seatplus\Auth\Http\Actions\Sso\FindOrCreateUserAction;
use Seatplus\Auth\Models\CharacterUser;
use Seatplus\Auth\Models\User;

class FindOrCreateUserActionTest extends TestCase
{
    use RefreshDatabase;
    /*User {#236 ▼
        +token: "maPOZCRtQCx8r6AWjzBMzwwmu-_E42IgqApzpUax8gYvVI0ucio9-pX99mEivX2fhct8ya4iS0ppgy_DJ0MXFA"
        +refreshToken: null
        +expiresIn: null
        +id: null
        +nickname: null
        +name: "Herpaderp Aldent"
        +email: null
        +avatar: "https://image.eveonline.com/Character/95725047_128.jpg"
        +user: array:8 [▼
    "CharacterID" => 95725047
    "CharacterName" => "Herpaderp Aldent"
    "ExpiresOn" => "2019-05-01T15:56:00"
    "Scopes" => "publicData"
    "TokenType" => "Character"
    "CharacterOwnerHash" => "TRVh/ElZN1oo+lsYJ5R+khBV+KE="
    "IntellectualProperty" => "EVE"
    "RefreshToken" => "XrlTt7w1DtQAZnQeYxPmTjmxYBwuO91ABsQCWSVEN7U"
  ]
  +"character_id": 95725047
        +"character_owner_hash": "TRVh/ElZN1oo+lsYJ5R+khBV+KE="
        +"scopes": "publicData"
        +"refresh_token": "XrlTt7w1DtQAZnQeYxPmTjmxYBwuO91ABsQCWSVEN7U"
        +"expires_on": Carbon @1556726160 {#245 ▶}
        }*/

    /**
     * @var \Faker\Generator
     */
    private $faker;

    public function setUp(): void
    {
        parent::setUp();

        $this->faker = Factory::create();
    }

    /** @test */
    public function createNewUser()
    {
        $socialiteUser = $this->createSocialUserMock();

        $this->assertDatabaseMissing('users', [
            'main_character_id' => $socialiteUser->character_id,
        ]);

        $user = (new FindOrCreateUserAction())->execute($socialiteUser);

        $this->assertDatabaseHas('users', [
            'main_character_id' => $socialiteUser->character_id,
        ]);

        $this->assertDatabaseHas('character_users', [
            'user_id'      => $user->id,
            'character_id' => $socialiteUser->character_id,
        ]);
    }

    /** @test */
    public function findExistingUserWithTwoCharacter()
    {

        // add 3 characters to test_user
        $this->test_user->characters()->createMany(
            factory(CharacterUser::class, 3)->make()->toArray()
        );

        $this->assertEquals(4, $this->test_user->characters->count());

        // select last character to login

        $secondary_character = $this->test_user->characters->last();

        $socialiteUser = $this->createSocialUserMock(
            $secondary_character->character_id,
            'SocialiteUserName',
            $secondary_character->character_owner_hash
        );

        $user = (new FindOrCreateUserAction())->execute($socialiteUser);

        $this->assertEquals($this->test_user->id, $user->id);

        $this->assertDatabaseMissing('users', [
            'name' => $socialiteUser->name,
        ]);

        $this->assertDatabaseHas('character_users', [
            'user_id'      => $this->test_user->id,
            'character_id' => $secondary_character->character_id,
        ]);
    }

    /** @test */
    public function dealWithChangedOwnerHash()
    {
        $this->assertEquals($this->test_user->characters->count(), 1);

        // 2. create character_users entry
        /*factory(CharacterUser::class)->create([
            'user_id' => $this->test_user->id,
            'character_id' => $this->test_user->id,
            'character_owner_hash' => $this->test_user->character_owner_hash
        ]);*/

        $socialiteUser = $this->createSocialUserMock(
            $this->test_user->characters->first()->character_id,
            $this->test_user->main_character,
            'anotherHashValue');

        // 3. find user

        $user = (new FindOrCreateUserAction())->execute($socialiteUser);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $this->test_user->id,
        ]);

        $this->assertDatabaseMissing('character_users', [
            'user_id'      => $this->test_user->id,
            'character_id' => $user->id,
        ]);
    }

    /** @test */
    public function dealWithTwoCharactersWithOneChangedOwnerHash()
    {

        // 1. Create secondary character
        $secondary_user = factory(CharacterUser::class)->make();

        // 2. assign secondary user to test_user
        $this->test_user->characters()->save($secondary_user);

        $this->assertEquals(2, $this->test_user->characters->count());

        // 3. find user

        $socialiteUser = $this->createSocialUserMock(
            $secondary_user->character_id,
            'someName',
            'anotherHashValue'
        );

        $user = (new FindOrCreateUserAction())->execute($socialiteUser);

        // 4. assert that two users exist

        //dd($secondary_user->character_id,User::first()->characters);

        $this->assertEquals(1, $user->characters->count());

        $this->assertEquals(2, CharacterUser::all()->count());

        $this->assertDatabaseHas('users', [
            'id' => $this->test_user->id,
        ]);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
        ]);

        //5. assert that secondary character is not affiliated to first user

        $this->assertDatabaseMissing('character_users', [
            'user_id'      => $this->test_user->id,
            'character_id' => $secondary_user->character_id,
        ]);

        $this->assertDatabaseHas('character_users', [
            'user_id'      => $user->id,
            'character_id' => $secondary_user->character_id,
        ]);
    }

    private function createSocialUserMock(int $character_id = null, string $name = null, string $character_owner_hash = null): SocialiteUser
    {
        $socialiteUser = $this->createMock(SocialiteUser::class);

        $socialiteUser->character_id = $character_id ?? $this->faker->numberBetween(90000000, 98000000);
        $socialiteUser->name = $name ?? $this->faker->name;
        $socialiteUser->character_owner_hash = $character_owner_hash ?? sha1($this->faker->text);

        return $socialiteUser;
    }
}
