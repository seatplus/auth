<?php


namespace Seatplus\Auth\Services;


use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

class GetRequiredScopesFromCharacters
{
    private Collection $required_scopes;

    public function execute(Collection $characters) : Collection
    {
        $this->required_scopes = collect();

        $characters->map(function ($character) {
            return [
                'corporation_scopes'             => $character->corporation->ssoScopes->selected_scopes ?? [],
                'alliance_scopes'                => $character->alliance->ssoScopes->selected_scopes ?? [],
                'application_corporation_scopes' => $character->application->corporation->ssoScopes->selected_scopes ?? [],
                'application_alliance_scopes'    => $character->application->corporation->alliance->ssoScopes->selected_scopes ?? [],
            ];
        })
            ->flatten(1)
            ->filter()
            ->each(function ($scope_array) {
                collect($scope_array)
                    ->flatten()
                    ->map(fn ($scope) => explode(',', $scope))
                    ->flatten()
                    ->each(fn ($scope) => $this->required_scopes->push($scope));

                if (Arr::get($scope_array, 'corporation')) {
                    $this->required_scopes->push('esi-characters.read_corporation_roles.v1');
                }
            });

        return $this->required_scopes->unique();
    }

}
