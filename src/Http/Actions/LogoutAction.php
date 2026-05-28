<?php

declare(strict_types=1);

namespace Seatplus\Auth\Http\Actions;

use Illuminate\Http\RedirectResponse;

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
