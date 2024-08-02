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

use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Laravel\Socialite\SocialiteManager;
use Seatplus\Auth\Listeners\ReactOnFreshRefreshToken;
use Seatplus\Auth\Listeners\UpdatingRefreshTokenListener;
use Seatplus\Auth\Models\User;
use Seatplus\Auth\Observers\ApplicationObserver;
use Seatplus\Auth\Observers\CharacterAffiliationObserver;
use Seatplus\Auth\Observers\SsoScopeObserver;
use Seatplus\Auth\Services\CacheService;
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
    public function boot(): void
    {
        //Add Migrations
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations/');

        // Add routes
        $this->loadRoutesFrom(__DIR__.'/../routes/routes.php');

        // Add event listeners
        $this->addEventListeners();

        // Add translations
        $this->loadTranslationsFrom(__DIR__ . '/../resources/lang', 'auth');

        // Add GateLogic
        Gate::before(function (User $user, string $ability): ?bool {
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

    }

    public function register(): void
    {
        // Register the Socialite Factory.
        // From: Laravel\Socialite\SocialiteServiceProvider
        $this->app->singleton('Laravel\Socialite\Contracts\Factory', function (Container $app) {
            return new SocialiteManager($app);
        });

        // Slap in the Eveonline Socialite Provider
        $socialite = $this->app->make('Laravel\Socialite\Contracts\Factory');

        $socialite->extend(
            'eveonline',
            function (Container $app) use ($socialite) {
                $config = config('services.eveonline');

                return $socialite->buildProvider(Provider::class, $config);
            }
        );

        $this->mergeConfigFrom(__DIR__.'/../config/permission.php', 'permission');
        $this->mergeConfigFrom(__DIR__.'/../config/auth.updateJobs.php', 'seatplus.updateJobs');
        $this->mergeConfigFrom(__DIR__.'/../config/auth.services.php', 'services');

        $this->setUserModel();
    }

    private function addEventListeners(): void
    {
        app('events')->listen(SocialiteWasCalled::class, EveonlineExtendSocialite::class);
        app('events')->listen(RefreshTokenCreated::class, ReactOnFreshRefreshToken::class);
        app('events')->listen(UpdatingRefreshTokenEvent::class, UpdatingRefreshTokenListener::class);
    }

    private function setUserModel(): void
    {
        // Set the User Model
        app('config')->set('auth.providers.users.model', User::class);
    }
}
