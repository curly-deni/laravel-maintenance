<?php

namespace Aesis\Maintenance\Console\Commands;

use Aesis\Maintenance\Services\MaintenancePolicy;
use Illuminate\Console\Command;

final class MaintenanceStatus extends Command
{
    protected $signature = 'maintenance:status {--json}';

    protected $description = 'Show the current maintenance phase and runtime state.';

    public function handle(MaintenancePolicy $policy): int
    {
        $status = $policy->payload();
        if ($this->option('json')) {
            $this->line((string) json_encode($status, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        } else {
            $this->table(['Key', 'Value'], collect($status)->map(fn ($value, $key): array => [$key, is_array($value) ? json_encode($value, JSON_UNESCAPED_UNICODE) : var_export($value, true)])->all());
        }

        return self::SUCCESS;
    }
}
