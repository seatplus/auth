<?php

namespace Seatplus\Auth\Listeners;

use Illuminate\Support\Facades\Cache;
use Seatplus\Auth\Models\CharacterUser;
use Seatplus\Eveapi\Events\RefreshTokenCreated;

class ReactOnFreshRefreshToken
{
    public function handle(RefreshTokenCreated $refresh_token_event)
    {

        $character_user = CharacterUser::query()
            ->where('character_id', $refresh_token_event->refresh_token->character_id)
            ->firstOrFail();

        $user_id = $character_user->user_id;
        Cache::tags(['characters_with_missing_scopes', $user_id])->flush();
    }
}