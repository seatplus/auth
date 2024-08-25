<?php

namespace Seatplus\Auth\Http\Actions;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LogoutAction
{
    public function __invoke(): RedirectResponse
    {
        auth()->logout();

        $session = session();
        $session->invalidate();
        $session->regenerateToken();

        return redirect('/');
    }
}
