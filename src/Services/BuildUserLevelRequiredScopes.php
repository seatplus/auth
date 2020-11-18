<?php


namespace Seatplus\Auth\Services;


use Illuminate\Support\Arr;
use Seatplus\Auth\Models\User;
use Seatplus\Eveapi\Models\SsoScopes;

class BuildUserLevelRequiredScopes
{
    public static function get(User $user) : array
    {

        $user->global_scope = $user->global_scope ?? Arr::get($user->getAttributes(), 'global_scope', SsoScopes::global()->select('selected_scopes')->first());

        return $user->characters->map(fn ($character) => collect([
            $character->corporation->ssoScopes ?? [],
            $character->alliance->ssoScopes ?? []
        ])->where('type','user'))
            ->filter(fn ($character) => $character->isNotEmpty())
            ->map(fn ($character) => $character->map(fn($scope) =>[
                $scope->selected_scopes,
            ]))
            ->concat([
                'user_application_corporation_scopes' => $user->application->corporation->ssoScopes->selected_scopes ?? [],
                'user_application_alliance_scopes'    => $user->application->corporation->alliance->ssoScopes->selected_scopes ?? [],
                'global_scopes'                  => json_decode($user->global_scope) ?? [],
            ])
            ->flatten()
            ->unique()
            ->toArray();
    }

}
