<?php

namespace Aesis\Maintenance\Models;

use Aesis\Maintenance\Enums\MaintenancePhase;
use Aesis\Maintenance\Enums\MaintenanceWindowStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * @property int $id
 * @property string $name
 * @property CarbonImmutable|null $announcement_at
 * @property CarbonImmutable|null $drain_starts_at
 * @property CarbonImmutable|null $maintenance_starts_at
 * @property CarbonImmutable|null $expected_end_at
 * @property MaintenanceWindowStatus|null $status
 * @property int|null $created_by
 */
class MaintenanceWindow extends Model
{
    protected $table = 'operations__maintenance_windows';

    public function getTable(): string
    {
        return (string) config('maintenance.table', $this->table);
    }

    public function save(array $options = []): bool
    {
        $connection = $this->getConnection();
        $lockTable = (string) config('maintenance.lock_table', 'operations__maintenance_window_locks');

        return $connection->transaction(function () use ($connection, $lockTable, $options): bool {
            $connection->table($lockTable)->where('id', 1)->update(['id' => 1]);

            return parent::save($options);
        });
    }

    protected $fillable = [
        'name',
        'announcement_at',
        'drain_starts_at',
        'maintenance_starts_at',
        'expected_end_at',
        'status',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => MaintenanceWindowStatus::class,
            'drain_starts_at' => 'immutable_datetime',
            'announcement_at' => 'immutable_datetime',
            'maintenance_starts_at' => 'immutable_datetime',
            'expected_end_at' => 'immutable_datetime',
        ];
    }

    public function phase(?CarbonImmutable $at = null): MaintenancePhase
    {
        if ($this->status === MaintenanceWindowStatus::CANCELLED || $this->status === MaintenanceWindowStatus::COMPLETED) {
            return MaintenancePhase::NORMAL;
        }

        $at ??= CarbonImmutable::now('UTC');

        if ($at->lt($this->drain_starts_at)) {
            return MaintenancePhase::SCHEDULED;
        }

        if ($at->lt($this->maintenance_starts_at)) {
            return MaintenancePhase::DRAINING;
        }

        if ($at->lt($this->expected_end_at)) {
            return MaintenancePhase::MAINTENANCE;
        }

        return MaintenancePhase::NORMAL;
    }

    public function isInProgress(?CarbonImmutable $at = null): bool
    {
        return $this->phase($at) !== MaintenancePhase::NORMAL;
    }

    protected static function booted(): void
    {
        static::saving(function (self $window): void {
            if (! $window->drain_starts_at || ! $window->maintenance_starts_at || ! $window->expected_end_at) {
                return;
            }

            if ($window->announcement_at && $window->announcement_at->isAfter($window->drain_starts_at)) {
                throw ValidationException::withMessages([
                    'announcement_at' => 'Анонс должен быть не позже начала запрета новых операций.',
                ]);
            }

            if ($window->drain_starts_at->gte($window->maintenance_starts_at)) {
                throw ValidationException::withMessages([
                    'drain_starts_at' => 'Запрет новых операций должен быть раньше начала maintenance.',
                ]);
            }

            if ($window->maintenance_starts_at->gte($window->expected_end_at)) {
                throw ValidationException::withMessages([
                    'expected_end_at' => 'Ожидаемое завершение должно быть позже начала maintenance.',
                ]);
            }

            if (($window->status ?? MaintenanceWindowStatus::SCHEDULED) !== MaintenanceWindowStatus::SCHEDULED) {
                return;
            }

            $overlapQuery = self::query()
                ->whereIn('status', [
                    MaintenanceWindowStatus::SCHEDULED->value,
                    MaintenanceWindowStatus::DRAINING->value,
                    MaintenanceWindowStatus::STARTED->value,
                ]);

            if ($window->exists) {
                $overlapQuery->whereKeyNot($window->getKey());
            }

            $overlap = $overlapQuery
                ->where('drain_starts_at', '<', $window->expected_end_at)
                ->where('expected_end_at', '>', $window->drain_starts_at)
                ->exists();

            if ($overlap) {
                throw ValidationException::withMessages([
                    'drain_starts_at' => 'Окно пересекается с другим активным окном.',
                ]);
            }
        });
    }
}
