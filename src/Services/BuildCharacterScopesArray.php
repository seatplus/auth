<?php


namespace Seatplus\Auth\Services;


use Illuminate\Support\Arr;
use Seatplus\Auth\Models\CharacterUser;
use Seatplus\Eveapi\Models\Character\CharacterInfo;

class BuildCharacterScopesArray
{
    public static function get(CharacterInfo $character, array $user_scopes = []): array
    {

        $user_scopes = $user_scopes
            ? $user_scopes
            : (optional(CharacterUser::whereCharacterId($character->character_id)->first())->user
                ? BuildUserLevelRequiredScopes::get(CharacterUser::whereCharacterId($character->character_id)->first()->user)
                : []
            );

        $character_array = [
            'character' => $character,
            'required_scopes' => collect([
                'corporation_scopes'             => $character->corporation->ssoScopes->selected_scopes ?? [],
                'alliance_scopes'                => $character->alliance->ssoScopes->selected_scopes ?? [],
                'character_application_corporation_scopes' => $character->application->corporation->ssoScopes->selected_scopes ?? [],
                'character_application_alliance_scopes'    => $character->application->corporation->alliance->ssoScopes->selected_scopes ?? [],
                'user_scope'                    => $user_scopes,
            ])->flatten(1)
                ->filter()
                ->unique()
                ->flatten(1)
                ->toArray(),
            'token_scopes' => $character->refresh_token->scopes ?? [],
        ];

        return Arr::add($character_array, 'missing_scopes', array_diff(Arr::get($character_array, 'required_scopes'), Arr::get($character_array, 'token_scopes')));
    }
}
