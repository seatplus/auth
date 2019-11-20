<?php

namespace Seatplus\Auth\Tests;

use Orchestra\Testbench\TestCase as OrchestraTestCase;
use Seatplus\Auth\AuthenticationServiceProvider;
use Seatplus\Auth\Models\User;
use Seatplus\Eveapi\EveapiServiceProvider;

abstract class TestCase extends OrchestraTestCase
{
    protected $test_user;

    protected function setUp(): void
    {
        parent::setUp();

        // setup database
        $this->setupDatabase($this->app);

        // setup factories
        $this->withFactories(__DIR__.'/database/factories');

        $this->test_user = factory(User::class)->create();
    }

    /**
     * Get application providers.
     *
     * @param \Illuminate\Foundation\Application $app
     *
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [
            AuthenticationServiceProvider::class,
            EveapiServiceProvider::class,
        ];
    }

    /**
     * @param \Illuminate\Foundation\Application $app
     */
    private function setupDatabase($app)
    {
        // Path to our migrations to load
        //$this->loadMigrationsFrom(__DIR__ . '/database/migrations');
        $this->artisan('migrate', ['--database' => 'testbench']);
    }

    /**
     * Define environment setup.
     *
     * @param \Illuminate\Foundation\Application $app
     *
     * @return void
     */
    protected function getEnvironmentSetUp($app)
    {
        // Use memory SQLite, cleans it self up
        $app['config']->set('database.default', 'testbench');
        $app['config']->set('database.connections.testbench', [
            'driver'   => 'sqlite',
            'database' => ':memory:',
            'prefix'   => '',
        ]);

        config(['app.debug' => true]);

        // Use test User model for users provider
        $app['config']->set('auth.providers.users.model', User::class);

        $app['config']->set('cache.prefix', 'seatplus_tests---');
    }
}
