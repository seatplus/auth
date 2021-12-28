<?php

namespace Seatplus\Auth\Listeners;

use Firebase\JWT\JWT;
use Illuminate\Support\Facades\Cache;
use Seatplus\Auth\Models\CharacterUser;
use Seatplus\Eveapi\Events\UpdatingRefreshTokenEvent;

class UpdatingRefreshTokenListener
{
    public function handle(UpdatingRefreshTokenEvent $refresh_token_event)
    {
        $refresh_token = $refresh_token_event->refresh_token;
        $original_scopes = $refresh_token->getOriginal('scopes');
        $new_scopes = $this->getScopes($refresh_token->token);

        if (array_diff($new_scopes, $original_scopes)) {

            $character_user = CharacterUser::query()
                ->where('character_id', $refresh_token->character_id)
                ->firstOrFail();

            $user_id = $character_user->user_id;
            Cache::tags(['characters_with_missing_scopes', $user_id])->flush();
        }
    }

    private function getScopes(string $jwt)
    {

        $jwt_payload_base64_encoded = explode('.', $jwt)[1];

        $jwt_payload = JWT::urlsafeB64Decode($jwt_payload_base64_encoded);

        $scopes = data_get(json_decode($jwt_payload), 'scp', []);

        return is_array($scopes) ? $scopes : [$scopes];
    }
}