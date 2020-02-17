<?php

/*
 * MIT License
 *
 * Copyright (c) 2019, 2020 Felix Huber
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

use Faker\Generator as Faker;
use Seatplus\Eveapi\Models\Character\CharacterInfo;
use Seatplus\Eveapi\Models\RefreshToken;

$factory->define(RefreshToken::class, function (Faker $faker) {
    return [
        'character_id'             => factory(CharacterInfo::class)->create(),
        'refresh_token'            => 'MmLZC2vwExCby2vbdgEVpOxXPUG3mIGfkQM5gl9IPtA',
        'scopes'                   => config('eveapi.scopes.minimum'),
        'expires_on'               => now()->addMinutes(10),
        'token'                    => '1|CfDJ8O+5Z0aH+aBNj61BXVSPWfj8DD6qBe5+pX4wW3xbFK7HHkOj+iGMNK77msOP0MvPSE/2h4v8AypOYxL9g+yUeiCixwOnY7arXZ+y0koNeujlyl9V5Zp1ju1Vr1/JZASzK6r/d16UMj4CVma/FqPYwjFtP0WpO24jokw1X4A2LQXm',
    ];
});
