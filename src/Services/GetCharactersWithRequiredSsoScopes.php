<?php


namespace Seatplus\Auth\Services;


use Illuminate\Support\Collection;
use Seatplus\Auth\Models\User;

class GetCharactersWithRequiredSsoScopes
{
    /**
     * @var \Seatplus\Auth\Models\User
     */
    private User $user;

    public function __construct()
    {
        $this->user = User::with(
            'characters.alliance.ssoScopes',
            'characters.corporation.ssoScopes',
            'characters.application.corporation.ssoScopes',
            'characters.application.corporation.alliance.ssoScopes'
        )
            ->where('id',auth()->user()->id)
            ->first();
    }

    public function execute() : Collection
    {
        return $this->user->characters->filter(function ($character) {

                return ($character->alliance->ssoScopes ?? false) || ($character->corporation->ssoScopes ?? false);
            })->isNotEmpty() ? $this->user->characters : collect();
    }

}
