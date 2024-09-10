<?php

it('builds provider with valid config', function () {
    $socialite = app('Laravel\Socialite\Contracts\Factory');

    $driver = $socialite->driver('eveonline');

    expect($driver)->toBeInstanceOf(\SocialiteProviders\Eveonline\Provider::class);
});
