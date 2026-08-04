<?php

use Seatplus\Auth\Models\CharacterUser;

test('one can change main character', function () {
    $secondary = CharacterUser::factory()->make();

    $this->test_user->characterUsers()->save($secondary);

    $this->test_user = $this->test_user->refresh();

    expect($this->test_user->characters)->toHaveCount(2);

    $this->assertNotEquals($secondary->character_id, $this->test_user->main_character_id);

    $this->actingAs($this->test_user)->put(route('change.main_character', [
        'new_character_id' => $secondary->character_id,
    ]))->assertRedirect();

    expect($this->test_user->refresh()->main_character_id)->toEqual($secondary->character_id);
});

test('one cannot change main character if character does not belong to user', function () {
    $secondary = CharacterUser::factory()->make();

    expect($this->test_user->characters)->toHaveCount(1);

    $this->assertNotEquals($secondary->character_id, $this->test_user->main_character_id);

    $this->actingAs($this->test_user)->put(route('change.main_character', [
        'new_character_id' => $secondary->character_id,
    ]))->assertForbidden();
});
