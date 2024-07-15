<?php

/*
 * MIT License
 *
 * Copyright (c) 2019, 2020, 2021 Felix Huber
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

namespace Seatplus\Auth\Http\Controllers;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Seatplus\Auth\Models\User;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

class MainCharacterController extends Controller
{
    public function change(Request $request): \Illuminate\Http\RedirectResponse
    {
        $request->validate(['character_id' => ['required', 'exists:character_infos,character_id']]);

        $character_id = $request->get('character_id');

        $user = User::whereHas('character_users', fn (Builder $query) => $query->where('character_id', $character_id))
            ->firstWhere('id', auth()->user()->getAuthIdentifier());

        abort_if(is_null($user), 403 ,'Unauthorized: supplied character_id does not belong to the current user');

        $user->changeMainCharacter($character_id);

        return back();
    }
}
