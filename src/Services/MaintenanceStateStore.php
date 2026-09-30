<?php

namespace Aesis\Maintenance\Services;

use Aesis\Maintenance\Enums\MaintenancePhase;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\File;
use RuntimeException;
use Throwable;

final class MaintenanceStateStore
{
    public const VERSION = 1;

    private function path(): string
    {
        return (string) (config('maintenance.state_path') ?? storage_path('app/maintenance/state.json'));
    }

    /** @return array<string, mixed>|null */
    public function read(): ?array
    {
        $path = $this->path();
        if (! File::exists($path)) {
            return null;
        }

        $state = json_decode(File::get($path), true, 512, JSON_THROW_ON_ERROR);
        if (! is_array($state) || ($state['version'] ?? null) !== self::VERSION) {
            throw new RuntimeException('Invalid maintenance state version.');
        }

        foreach ([
            'window_id',
            'mode',
            'name',
            'announcement_visible',
            'announcement_at',
            'drain_starts_at',
            'maintenance_starts_at',
            'expected_end_at',
        ] as $key) {
            if (! array_key_exists($key, $state)) {
                throw new RuntimeException("Missing maintenance state field: {$key}.");
            }
        }

        if (! in_array($state['mode'] ?? null, [
            MaintenancePhase::NORMAL->value,
            MaintenancePhase::SCHEDULED->value,
            MaintenancePhase::DRAINING->value,
            MaintenancePhase::MAINTENANCE->value,
        ], true)) {
            throw new RuntimeException('Invalid maintenance state mode.');
        }

        if (! is_int($state['window_id'] ?? null) && ($state['window_id'] ?? null) !== null) {
            throw new RuntimeException('Invalid maintenance state window ID.');
        }

        if (! is_string($state['name'] ?? null) || ! is_bool($state['announcement_visible'] ?? null)) {
            throw new RuntimeException('Invalid maintenance state payload.');
        }

        foreach (['announcement_at', 'drain_starts_at', 'maintenance_starts_at', 'expected_end_at'] as $key) {
            $timestamp = $state[$key] ?? null;
            $nullable = $key === 'announcement_at';

            if (($nullable && $timestamp === null) || (is_string($timestamp) && $this->isValidTimestamp($timestamp))) {
                continue;
            }

            throw new RuntimeException("Invalid maintenance state timestamp: {$key}.");
        }

        return $state;
    }

    private function isValidTimestamp(string $timestamp): bool
    {
        try {
            CarbonImmutable::parse($timestamp);

            return true;
        } catch (Throwable) {
            return false;
        }
    }

    /** @param array<string, mixed> $state */
    public function write(array $state): void
    {
        $path = $this->path();
        File::ensureDirectoryExists(dirname($path));
        File::replace($path, (string) json_encode($state, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
    }

    public function clear(): void
    {
        $path = $this->path();
        if (File::exists($path)) {
            File::delete($path);
        }
    }
}
