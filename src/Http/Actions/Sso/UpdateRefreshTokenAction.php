<?php

namespace Seatplus\Auth\Http\Actions\Sso;

use Laravel\Socialite\Two\User as EveUser;
use Seatplus\Eveapi\Models\RefreshToken;

class UpdateRefreshTokenAction
{
    public function execute(EveUser $eve_data)
    {
        RefreshToken::withTrashed()->firstOrNew(['character_id' => $eve_data->character_id])
            ->fill([
                'refresh_token' => $eve_data->refresh_token,
                'scopes'        => explode(' ', $eve_data->scopes),
                'token'         => $eve_data->token,
                'expires_on'    => $eve_data->expires_on,
            ])
            ->save();

        // restore soft deleted token if any
        RefreshToken::onlyTrashed()->where('character_id', $eve_data->character_id)->restore();

        //TODO: if user was deactivated reactivate him https://github.com/eveseat/web/blob/a0c1dd6a73c10e91813276cd57b5b51460bdfc43/src/Http/Controllers/Auth/SsoController.php#L264
    }
}
