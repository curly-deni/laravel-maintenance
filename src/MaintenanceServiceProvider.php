<?php

namespace Aesis\Maintenance;

use Aesis\Maintenance\Console\Commands\CancelMaintenanceWindow;
use Aesis\Maintenance\Console\Commands\CompleteMaintenanceWindow;
use Aesis\Maintenance\Console\Commands\MaintenanceStatus;
use Aesis\Maintenance\Console\Commands\SyncMaintenanceState;
use Aesis\Maintenance\Http\Middleware\RejectNewWorkDuringMaintenance;
use Illuminate\Routing\Router;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

final class MaintenanceServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package
            ->name('laravel-maintenance')
            ->hasConfigFile('maintenance')
            ->hasMigration('create_maintenance_windows_table')
            ->hasCommands([
                CancelMaintenanceWindow::class,
                CompleteMaintenanceWindow::class,
                MaintenanceStatus::class,
                SyncMaintenanceState::class,
            ]);
    }

    public function packageBooted(): void
    {
        $this->app->make(Router::class)->aliasMiddleware('maintenance.new-work', RejectNewWorkDuringMaintenance::class);
    }
}
