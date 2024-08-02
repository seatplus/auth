<?php

namespace Seatplus\Auth\Http\Actions;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LogoutAction
{
    public function __invoke(Request $request): RedirectResponse
    {
        \Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

}
