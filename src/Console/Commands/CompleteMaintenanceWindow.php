<?php

namespace Aesis\Maintenance\Console\Commands;

use Aesis\Maintenance\Enums\MaintenanceWindowStatus;
use Aesis\Maintenance\Models\MaintenanceWindow;
use Aesis\Maintenance\Services\MaintenanceStatePublisher;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

final class CompleteMaintenanceWindow extends Command
{
    protected $signature = 'maintenance:complete {window : Maintenance window ID}';

    protected $description = 'Mark a maintenance window as completed.';

    public function handle(MaintenanceStatePublisher $publisher): int
    {
        $window = MaintenanceWindow::query()->findOrFail((int) $this->argument('window'));
        if (! in_array($window->status, [
            MaintenanceWindowStatus::SCHEDULED,
            MaintenanceWindowStatus::DRAINING,
            MaintenanceWindowStatus::STARTED,
        ], true)) {
            throw ValidationException::withMessages(['window' => 'Это окно уже завершено или отменено.']);
        }

        $window->update(['status' => MaintenanceWindowStatus::COMPLETED]);
        $publisher->sync();
        $this->info("Maintenance window {$window->id} completed.");

        return self::SUCCESS;
    }
}
