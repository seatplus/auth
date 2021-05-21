<?php


namespace Seatplus\Auth\Tests\Feature\MainCharacter;



use Seatplus\Auth\Models\CharacterUser;
use Seatplus\Auth\Tests\TestCase;

class MainCharacterTest extends TestCase
{
    /** @test */
    public function oneCanChangeMainCharacter()
    {

        $secondary = CharacterUser::factory()->make();

        $this->test_user->character_users()->save($secondary);

        $this->test_user = $this->test_user->refresh();

        $this->assertCount(2, $this->test_user->characters);

        $this->assertNotEquals($secondary->character_id, $this->test_user->main_character_id);

        $this->actingAs($this->test_user)->post(route('change.main_character'), [
            'character_id' => $secondary->character_id
        ])->assertRedirect();

        $this->assertEquals($secondary->character_id, $this->test_user->refresh()->main_character_id);
    }

    /** @test */
    public function oneCannotChangeMainCharacterIfCharacterDoesNotBelongToUser()
    {

        $secondary = CharacterUser::factory()->make();

        $this->assertCount(1, $this->test_user->characters);

        $this->assertNotEquals($secondary->character_id, $this->test_user->main_character_id);

        $this->actingAs($this->test_user)->post(route('change.main_character'), [
            'character_id' => $secondary->character_id
        ])->assertUnauthorized();
    }

}