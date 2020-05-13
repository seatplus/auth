<?php


namespace Seatplus\Auth\Http\Controllers\Auth;


use Laravel\Socialite\Contracts\Factory as Socialite;
use Seatplus\Auth\Http\Controllers\Controller;
use Seatplus\Auth\Services\GetSRequiredScopes;
use Seatplus\Eveapi\Models\RefreshToken;

class StepUpController extends Controller
{
    /**
     * Redirect the user to the Eve Online authentication page.
     *
     * @param \Laravel\Socialite\Contracts\Factory       $social
     *
     * @param \Seatplus\Auth\Services\GetSRequiredScopes $required_scopes
     *
     * @param int                                        $character_id
     *
     * @return \Symfony\Component\HttpFoundation\RedirectResponse
     */
    public function __invoke(Socialite $social, GetSRequiredScopes $required_scopes, int $character_id)
    {

        if(! $this->isCharacterAssociatedToCurrentUser($character_id)) {
            return redirect()->back()->with('error', 'character must belong to your account');
        }

        $add_scopes = explode(',', request()->query('add_scopes'));

        $scopes = collect(RefreshToken::find($character_id)->scopes)->merge($add_scopes)->toArray();

        session([
            'rurl'       => session()->previousUrl(),
            'sso_scopes' => $scopes,
            'step_up' => $character_id
        ]);

        return $social
            ->driver('eveonline')
            ->scopes($scopes)
            ->redirect();
    }

    private function isCharacterAssociatedToCurrentUser(int $character_id) : bool
    {
        return auth()->user()->characters->pluck('character_id')->contains($character_id);
    }
}
