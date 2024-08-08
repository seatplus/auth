<?php

use Illuminate\Support\Facades\Cache;
use Seatplus\Auth\Models\CharacterUser;
use Seatplus\Eveapi\Models\RefreshToken;

it('forgets user permission object when a new character is added', function () {
    $user_id = test()->test_user->id;

    Cache::shouldReceive('forget')
        ->once()
        ->with("user_permissions_{$user_id}");

    $character_user = CharacterUser::factory()->create([
        'user_id' => $user_id,
        'character_id' => faker()->randomNumber(),
    ]);

    RefreshToken::factory()->create(['character_id' => $character_user->character_id]);
});

it('forgets user permission object when refresh_token scopes are updated', function () {
    $user_id = test()->test_user->id;

    Cache::shouldReceive('forget')
        ->once()
        ->with("user_permissions_{$user_id}");

    $refresh_token = test()->test_character->refresh_token;
    $refresh_token->token = createSocialiteUser($refresh_token->character_id, ['foo', 'bar'])->token;

    $refresh_token->save();
});
