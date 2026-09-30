<?php

namespace Aesis\Maintenance\Tests;

use Aesis\Maintenance\MaintenanceServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        $migration = require __DIR__.'/../database/migrations/create_maintenance_windows_table.php.stub';
        $migration->up();

    }

    protected function getPackageProviders($app)
    {
        return [
            MaintenanceServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        config()->set('database.connections.testing', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);
        config()->set('maintenance.state_path', sys_get_temp_dir().'/laravel-maintenance-tests/state.json');
        config()->set('maintenance.flag_path', sys_get_temp_dir().'/laravel-maintenance-tests/maintenance.flag');
    }
}
