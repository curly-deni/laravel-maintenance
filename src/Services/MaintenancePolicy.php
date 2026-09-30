<?php

namespace Aesis\Maintenance\Services;

use Aesis\Maintenance\Enums\MaintenancePhase;
use Throwable;

final class MaintenancePolicy
{
    public function __construct(private readonly MaintenanceStateStore $store) {}

    /** @return array<string, mixed>|null */
    public function state(): ?array
    {
        try {
            return $this->store->read();
        } catch (Throwable $exception) {
            report($exception);

            return [
                'version' => MaintenanceStateStore::VERSION,
                'window_id' => null,
                'mode' => MaintenancePhase::MAINTENANCE->value,
                'name' => 'maintenance',
                'announcement_visible' => false,
                'announcement_at' => null,
                'drain_starts_at' => null,
                'maintenance_starts_at' => null,
                'expected_end_at' => null,
            ];
        }
    }

    public function mode(): string
    {
        return $this->state()['mode'] ?? MaintenancePhase::NORMAL->value;
    }

    public function phase(): string
    {
        return $this->mode();
    }

    /** @return array<string, mixed> */
    public function payload(): array
    {
        $state = $this->state();

        return [
            'phase' => $state['mode'] ?? MaintenancePhase::NORMAL->value,
            'server_time' => now('UTC')->toISOString(),
            'announcement_visible' => $state['announcement_visible'] ?? false,
            'window' => ($state && $state['window_id'] !== null) ? [
                'id' => $state['window_id'],
                'announcement_at' => $state['announcement_at'],
                'drain_starts_at' => $state['drain_starts_at'],
                'maintenance_starts_at' => $state['maintenance_starts_at'],
                'expected_end_at' => $state['expected_end_at'],
            ] : null,
        ];
    }
}
