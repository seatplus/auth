<?php

use Faker\Generator as Faker;
use Seatplus\Eveapi\Models\Character\CharacterInfo;
use Seatplus\Eveapi\Models\RefreshToken;

$factory->define(RefreshToken::class, function (Faker $faker) {

    return [
        'character_id'    => factory(CharacterInfo::class)->create(),
        'refresh_token'            => 'MmLZC2vwExCby2vbdgEVpOxXPUG3mIGfkQM5gl9IPtA',
        'scopes'  => config('eveapi.scopes.minimum'),
        'expires_on'        => now()->addMinutes(10),
        'token'          => '1|CfDJ8O+5Z0aH+aBNj61BXVSPWfj8DD6qBe5+pX4wW3xbFK7HHkOj+iGMNK77msOP0MvPSE/2h4v8AypOYxL9g+yUeiCixwOnY7arXZ+y0koNeujlyl9V5Zp1ju1Vr1/JZASzK6r/d16UMj4CVma/FqPYwjFtP0WpO24jokw1X4A2LQXm',
    ];
});
