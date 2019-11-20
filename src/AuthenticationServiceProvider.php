<?php

namespace Seatplus\Auth;

use Illuminate\Support\ServiceProvider;
use Laravel\Passport\Http\Middleware\CreateFreshApiToken;
use Laravel\Socialite\SocialiteManager;
use Seatplus\Auth\Extentions\EveOnlineProvider;

class AuthenticationServiceProvider extends ServiceProvider
{
    public function boot()
    {
        //Add Migrations
        $this->loadMigrationsFrom(__DIR__.'/database/migrations/');

        // Add routes
        $this->loadRoutesFrom(__DIR__.'/Http/routes.php');

        // Add Middleware
        $this->addMiddleware();
    }

    public function register()
    {
        // Register the Socialite Factory.
        // From: Laravel\Socialite\SocialiteServiceProvider
        $this->app->singleton('Laravel\Socialite\Contracts\Factory', function ($app) {
            return new SocialiteManager($app);
        });

        // Slap in the Eveonline Socialite Provider
        $eveonline = $this->app->make('Laravel\Socialite\Contracts\Factory');

        $eveonline->extend('eveonline',
            function ($app) use ($eveonline) {
                $config = $app['config']['services.eveonline'];

                return $eveonline->buildProvider(EveOnlineProvider::class, $config);
            }
        );

        $this->mergeConfigFrom(
            __DIR__.'/config/permission.php', 'permission'
        );
    }

    private function addMiddleware()
    {
        $router = $this->app['router'];

        // Add create fresh api token
        $router->pushMiddlewareToGroup('web', CreateFreshApiToken::class);
    }
}
