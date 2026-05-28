<?php

use Laravel\Socialite\Contracts\Factory;
use SocialiteProviders\Eveonline\Provider;

it('builds provider with valid config', function () {
    $socialite = app(Factory::class);

    $driver = $socialite->driver('eveonline');

    expect($driver)->toBeInstanceOf(Provider::class);
});
