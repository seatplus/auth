<?php

use Illuminate\Support\Facades\Cache;
use Seatplus\Auth\Models\CharacterUser;
use Seatplus\Eveapi\Models\RefreshToken;

it('flush characters_with_missing_scopes cache for user when a new character is added', function () {
    $user_id = test()->test_user->id;

    Cache::shouldReceive('tags')->with(['characters_with_missing_scopes', $user_id])->andReturnSelf();
    Cache::shouldReceive('flush')->once();

    $character_user = CharacterUser::factory()->create([
        'user_id' => $user_id,
        'character_id' => faker()->randomNumber(),
    ]);

    RefreshToken::factory()->create(['character_id' => $character_user->character_id]);
});

it('flush characters_with_missing_scopes cache for user when refresh_token scopes are updated', function () {
    $user_id = test()->test_user->id;

    Cache::shouldReceive('tags')->with(['characters_with_missing_scopes', $user_id])->andReturnSelf();
    Cache::shouldReceive('flush')->once();

    $refresh_token = test()->test_character->refresh_token;
    $refresh_token->token = createSocialiteUser($refresh_token->character_id, ['foo', 'bar'])->token;

    $refresh_token->save();
});
