<?php


use Seatplus\Auth\Models\CharacterUser;

test('one can change main character', function () {
    $secondary = CharacterUser::factory()->make();

    test()->test_user->character_users()->save($secondary);

    test()->test_user = test()->test_user->refresh();

    expect(test()->test_user->characters)->toHaveCount(2);

    test()->assertNotEquals($secondary->character_id, test()->test_user->main_character_id);

    test()->actingAs(test()->test_user)->post(route('change.main_character'), [
        'character_id' => $secondary->character_id,
    ])->assertRedirect();

    expect(test()->test_user->refresh()->main_character_id)->toEqual($secondary->character_id);
});

test('one cannot change main character if character does not belong to user', function () {
    $secondary = CharacterUser::factory()->make();

    expect(test()->test_user->characters)->toHaveCount(1);

    test()->assertNotEquals($secondary->character_id, test()->test_user->main_character_id);

    test()->actingAs(test()->test_user)->post(route('change.main_character'), [
        'character_id' => $secondary->character_id,
    ])->assertUnauthorized();
});
