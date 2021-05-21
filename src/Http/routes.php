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

use Illuminate\Support\Facades\Route;
use Seatplus\Auth\Http\Controllers\Auth\LoginController;
use Seatplus\Auth\Http\Controllers\Auth\SsoController;
use Seatplus\Auth\Http\Controllers\Auth\StepUpController;
use Seatplus\Auth\Http\Controllers\MainCharacterController;

Route::prefix('auth')
    ->middleware('web')
    ->group(function () {

        // Auth
        Route::get('login', [LoginController::class, 'showLoginForm'])->name('auth.login');

        Route::get('logout', [LoginController::class, 'logout'])->name('auth.logout');

        // SSO
        Route::get('/eve/sso/', [SsoController::class, 'redirectToProvider'])->name('auth.eve');
        Route::get('/eve/sso/{character_id}/step_up', StepUpController::class)->name('auth.eve.step_up');

        Route::get('/eve/callback', [SsoController::class, 'handleProviderCallback'])->name('auth.eve.callback');

        // MainCharacter
        Route::post('main_character/change', [MainCharacterController::class, 'change'])
            ->name('change.main_character');
    });
