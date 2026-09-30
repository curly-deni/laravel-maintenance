<?php

namespace Aesis\Maintenance\Console\Commands;

use Aesis\Maintenance\Enums\MaintenancePhase;
use Aesis\Maintenance\Enums\MaintenanceWindowStatus;
use Aesis\Maintenance\Models\MaintenanceWindow;
use Aesis\Maintenance\Services\MaintenanceStatePublisher;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

final class CancelMaintenanceWindow extends Command
{
    protected $signature = 'maintenance:cancel {window : Maintenance window ID}';

    protected $description = 'Cancel a scheduled maintenance window before maintenance starts.';

    public function handle(MaintenanceStatePublisher $publisher): int
    {
        $window = MaintenanceWindow::query()->findOrFail((int) $this->argument('window'));
        if ($window->status !== MaintenanceWindowStatus::SCHEDULED
            || $window->phase() !== MaintenancePhase::SCHEDULED) {
            throw ValidationException::withMessages(['window' => 'Это окно уже нельзя отменить.']);
        }

        $window->update(['status' => MaintenanceWindowStatus::CANCELLED]);
        $publisher->sync();
        $this->info("Maintenance window {$window->id} cancelled.");

        return self::SUCCESS;
    }
}
