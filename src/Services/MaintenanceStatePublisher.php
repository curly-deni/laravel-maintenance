<?php

namespace Aesis\Maintenance\Services;

use Aesis\Maintenance\Enums\MaintenancePhase;
use Aesis\Maintenance\Enums\MaintenanceWindowStatus;
use Aesis\Maintenance\Events\MaintenanceAvailabilityChanged;
use Aesis\Maintenance\Models\MaintenanceWindow;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use Throwable;

final class MaintenanceStatePublisher
{
    public function __construct(private readonly MaintenanceStateStore $store) {}

    /** @return array<string, mixed>|null */
    public function sync(): ?array
    {
        $at = CarbonImmutable::now('UTC');
        $window = $this->findWindow($at);
        $current = $this->readCurrentState();

        if ($window === null) {
            $this->store->clear();
            $this->syncNginxFlag(null);
            $this->broadcastIfChanged($current, null);

            return null;
        }

        $status = match (true) {
            $at->greaterThanOrEqualTo($window->expected_end_at) => MaintenanceWindowStatus::COMPLETED,
            $at->greaterThanOrEqualTo($window->maintenance_starts_at) => MaintenanceWindowStatus::STARTED,
            $at->greaterThanOrEqualTo($window->drain_starts_at) => MaintenanceWindowStatus::DRAINING,
            default => MaintenanceWindowStatus::SCHEDULED,
        };

        // expected_end_at is the hard cutoff. Once it is reached, reopen
        // automatically; readiness remains an operational diagnostic only.
        if ($status === MaintenanceWindowStatus::COMPLETED) {
            $window->update(['status' => MaintenanceWindowStatus::COMPLETED]);
            $this->store->clear();
            $this->syncNginxFlag(null);
            $this->broadcastIfChanged($current, null);

            return null;
        }

        if ($window->status !== $status) {
            $window->update(['status' => $status]);
        }

        $state = [
            'version' => MaintenanceStateStore::VERSION,
            'window_id' => $window->id,
            'mode' => $this->modeFor($window->phase($at))->value,
            'name' => $window->name,
            'announcement_visible' => $window->announcement_at === null || $at->greaterThanOrEqualTo($window->announcement_at),
            'announcement_at' => $window->announcement_at?->toISOString(),
            'drain_starts_at' => $window->drain_starts_at->toISOString(),
            'maintenance_starts_at' => $window->maintenance_starts_at->toISOString(),
            'expected_end_at' => $window->expected_end_at->toISOString(),
        ];

        if ($current !== $state) {
            $this->store->write($state);
            MaintenanceAvailabilityChanged::dispatch();
        }
        $this->syncNginxFlag($state['mode']);

        return $state;
    }

    /** @return array<string, mixed>|null */
    private function readCurrentState(): ?array
    {
        try {
            return $this->store->read();
        } catch (Throwable $exception) {
            report($exception);

            return null;
        }
    }

    /** @param array<string, mixed>|null $current */
    private function broadcastIfChanged(?array $current, ?array $next): void
    {
        if ($current !== $next) {
            MaintenanceAvailabilityChanged::dispatch();
        }
    }

    private function syncNginxFlag(?string $mode): void
    {
        $path = (string) (config('maintenance.flag_path') ?? storage_path('app/maintenance/maintenance.flag'));
        if ($mode === MaintenancePhase::MAINTENANCE->value) {
            File::ensureDirectoryExists(dirname($path));
            File::put($path, $mode.PHP_EOL);

            return;
        }
        if (File::exists($path)) {
            File::delete($path);
        }
    }

    private function findWindow(CarbonImmutable $at): ?MaintenanceWindow
    {
        $active = MaintenanceWindow::query()
            ->whereIn('status', [MaintenanceWindowStatus::SCHEDULED->value, MaintenanceWindowStatus::DRAINING->value, MaintenanceWindowStatus::STARTED->value])
            ->where('drain_starts_at', '<=', $at)
            ->where('expected_end_at', '>', $at)
            ->orderByDesc('drain_starts_at')
            ->first();

        return $active ?? MaintenanceWindow::query()
            ->where('status', MaintenanceWindowStatus::SCHEDULED->value)
            ->where('drain_starts_at', '>', $at)
            ->orderBy('drain_starts_at')
            ->first();
    }

    private function modeFor(MaintenancePhase $phase): MaintenancePhase
    {
        return match ($phase) {
            MaintenancePhase::SCHEDULED => MaintenancePhase::SCHEDULED,
            MaintenancePhase::DRAINING => MaintenancePhase::DRAINING,
            MaintenancePhase::MAINTENANCE => MaintenancePhase::MAINTENANCE,
            default => MaintenancePhase::NORMAL,
        };
    }
}
