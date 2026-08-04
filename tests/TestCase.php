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

namespace Seatplus\Auth\Tests;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Seatplus\Auth\AuthenticationServiceProvider;
use Seatplus\Auth\Models\User;
use Seatplus\Eveapi\EveapiServiceProvider;
use Spatie\Permission\PermissionServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    public User $test_user;

    public $test_character;

    protected function setUp(): void
    {
        // Hard stop: never run the suite against the dev database. The phpunit
        // <env DB_DATABASE="laravel" force="true"> override has been observed to be bypassed
        // in the local dev container (the shell exports DB_DATABASE=seatplus), in which case
        // RefreshDatabase would migrate:fresh the dev DB and wipe it. Abort loudly before
        // parent::setUp() boots anything or a factory touches the connection.
        if (env('DB_DATABASE') === 'seatplus') {
            throw new \RuntimeException('Test suite resolved DB_DATABASE=seatplus (the dev database) — aborting to avoid wiping dev data. The phpunit force override did not hold.');
        }

        parent::setUp();

        Model::shouldBeStrict();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => match (true) {
                Str::startsWith($modelName, 'Seatplus\Auth') => 'Seatplus\\Auth\\Database\\Factories\\'.class_basename($modelName).'Factory',
                Str::startsWith($modelName, 'Seatplus\Eveapi') => 'Seatplus\\Eveapi\\Database\\Factories\\'.class_basename($modelName).'Factory',
                // Only the two seatplus namespaces have factories here. Fail loudly rather
                // than guessing a namespace (this arm was previously an implicit
                // UnhandledMatchError).
                default => throw new \InvalidArgumentException("No factory namespace known for model [$modelName]."),
            }
        );

        // Make sure no jobs are being pushed to queues
        Queue::fake();

        // setup database
        $this->setupDatabase($this->app);

        Event::fakeFor(function () {
            $this->test_user = User::factory()->create();

            $this->test_character = $this->test_user->characters->first();
        });
    }

    /**
     * Get application providers.
     *
     * @param  Application  $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            EveapiServiceProvider::class,
            AuthenticationServiceProvider::class,
            PermissionServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    private function setupDatabase($app)
    {
        // Path to our migrations to load
        // $this->loadMigrationsFrom(__DIR__ . '/database/migrations');
        $this->artisan('migrate');
    }

    /**
     * Define environment setup.
     *
     * @param  Application  $app
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        config(['app.debug' => true]);
        config(['activitylog.table_name' => 'activity_log']);

        // Use test User model for users provider
        $app['config']->set('auth.providers.users.model', User::class);

        // $app['config']->set('cache.prefix', 'seatplus_tests---');
    }
}
