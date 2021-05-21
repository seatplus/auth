<?php


namespace Seatplus\Auth\Http\Controllers;


use Exception;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Seatplus\Auth\Models\User;

class MainCharacterController extends Controller
{
    public function change(Request $request)
    {
        $request->validate(['character_id' => ['required', 'exists:character_infos,character_id']]);

        $character_id = $request->get('character_id');

        $user = User::whereHas('character_users', fn(Builder $query) => $query->whereCharacterId($character_id))
            ->firstWhere('id', auth()->user()->getAuthIdentifier());

        if(is_null($user))
            return response('Unauthorized: supplied character_id does not belong to the current user',401);

        $user->changeMainCharacter($character_id);

        return back();
    }

}