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

namespace Seatplus\Auth;

use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\SocialiteManager;
use Seatplus\Auth\Listeners\ReactOnFreshRefreshToken;
use Seatplus\Auth\Listeners\UpdatingRefreshTokenListener;
use Seatplus\Auth\Models\User;
use Seatplus\Auth\Observers\ApplicationObserver;
use Seatplus\Auth\Observers\CharacterAffiliationObserver;
use Seatplus\Auth\Observers\SsoScopeObserver;
use Seatplus\Eveapi\Events\RefreshTokenCreated;
use Seatplus\Eveapi\Events\UpdatingRefreshTokenEvent;
use Seatplus\Eveapi\Models\Application;
use Seatplus\Eveapi\Models\Character\CharacterAffiliation;
use Seatplus\Eveapi\Models\SsoScopes;
use SocialiteProviders\Eveonline\EveonlineExtendSocialite;
use SocialiteProviders\Eveonline\Provider;
use SocialiteProviders\Manager\SocialiteWasCalled;
use Spatie\Permission\Exceptions\PermissionDoesNotExist;

class AuthenticationServiceProvider extends ServiceProvider
{
    public function boot()
    {
        //Add Migrations
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations/');

        // Add routes
        $this->loadRoutesFrom(__DIR__ . '/../routes/routes.php');

        // Add event listeners
        $this->addEventListeners();

        // Add GateLogic
        Gate::before(function ($user, $ability) {
            try {
                return $user->hasPermissionTo('superuser') ? true : null;
            } catch (PermissionDoesNotExist) {
                return null;
            }
        });

        // Add observer
        CharacterAffiliation::observe(CharacterAffiliationObserver::class);
        SsoScopes::observe(SsoScopeObserver::class);
        Application::observe(ApplicationObserver::class);

        // Add Event Listeners
        $this->app->events->listen(RefreshTokenCreated::class, ReactOnFreshRefreshToken::class);
        $this->app->events->listen(UpdatingRefreshTokenEvent::class, UpdatingRefreshTokenListener::class);
    }

    public function register()
    {
        // Register the Socialite Factory.
        // From: Laravel\Socialite\SocialiteServiceProvider
        $this->app->singleton('Laravel\Socialite\Contracts\Factory', function ($app) {
            return new SocialiteManager($app);
        });

        // Slap in the Eveonline Socialite Provider
        $socialite = $this->app->make('Laravel\Socialite\Contracts\Factory');

        $socialite->extend(
            'eveonline',
            function ($app) use ($socialite) {
                $config = $app['config']['services.eveonline'];

                return $socialite->buildProvider(Provider::class, $config);
            }
        );

        $this->mergeConfigFrom(__DIR__ . '/../config/permission.php', 'permission');
        $this->mergeConfigFrom(__DIR__ . '/../config/auth.updateJobs.php', 'seatplus.updateJobs');
        $this->mergeConfigFrom(__DIR__ . '/../config/auth.services.php', 'services');

        $this->setUserModel();
    }

    private function addEventListeners()
    {
        $this->app->events->listen(SocialiteWasCalled::class, EveonlineExtendSocialite::class);
    }

    private function setUserModel()
    {
        // Set the User Model
        $this->app->config->set('auth.providers.users.model', User::class);
    }
}
