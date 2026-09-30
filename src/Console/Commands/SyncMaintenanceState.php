<?php

namespace Aesis\Maintenance\Console\Commands;

use Aesis\Maintenance\Services\MaintenanceStatePublisher;
use Illuminate\Console\Command;

final class SyncMaintenanceState extends Command
{
    protected $signature = 'maintenance:sync-state';

    protected $description = 'Synchronize the maintenance runtime state file from the database.';

    public function handle(MaintenanceStatePublisher $publisher): int
    {
        $state = $publisher->sync();

        $this->info($state ? "Maintenance state: {$state['mode']} (window {$state['window_id']})." : 'Maintenance state cleared.');

        return self::SUCCESS;
    }
}
