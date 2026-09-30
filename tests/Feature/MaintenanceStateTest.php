<?php

use Aesis\Maintenance\Enums\MaintenancePhase;
use Aesis\Maintenance\Enums\MaintenanceWindowStatus;
use Aesis\Maintenance\Events\MaintenanceAvailabilityChanged;
use Aesis\Maintenance\Models\MaintenanceWindow;
use Aesis\Maintenance\Services\MaintenancePolicy;
use Aesis\Maintenance\Services\MaintenanceStatePublisher;
use Aesis\Maintenance\Services\MaintenanceStateStore;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;

beforeEach(function (): void {
    app(MaintenanceStateStore::class)->clear();
});

afterEach(function (): void {
    app(MaintenanceStateStore::class)->clear();
    CarbonImmutable::setTestNow();
});

function makeMaintenanceWindow(): MaintenanceWindow
{
    return MaintenanceWindow::query()->create([
        'drain_starts_at' => '2026-08-31 01:30:00+00',
        'maintenance_starts_at' => '2026-08-31 02:00:00+00',
        'expected_end_at' => '2026-08-31 03:00:00+00',
    ]);
}

test('maintenance window table name comes from configuration', function (): void {
    config()->set('maintenance.table', 'custom_maintenance_windows');
    config()->set('maintenance.lock_table', 'custom_maintenance_window_locks');

    expect((new MaintenanceWindow)->getTable())->toBe('custom_maintenance_windows');

    $migration = require __DIR__.'/../../database/migrations/create_maintenance_windows_table.php.stub';

    try {
        $migration->up();

        expect(Schema::hasTable('custom_maintenance_windows'))->toBeTrue();
    } finally {
        $migration->down();
        config()->set('maintenance.table', 'operations__maintenance_windows');
        config()->set('maintenance.lock_table', 'operations__maintenance_window_locks');
    }
});

test('missing state allows normal traffic without a database query', function (): void {
    DB::enableQueryLog();
    DB::flushQueryLog();

    expect(app(MaintenancePolicy::class)->phase())->toBe(MaintenancePhase::NORMAL->value)
        ->and(DB::getQueryLog())->toBeEmpty();
});

test('publisher writes the ready mode and policy only reads the file', function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-31 02:30:00Z'));
    $window = makeMaintenanceWindow();

    app(MaintenanceStatePublisher::class)->sync();
    DB::enableQueryLog();
    DB::flushQueryLog();

    expect(app(MaintenancePolicy::class)->phase())->toBe(MaintenancePhase::MAINTENANCE->value)
        ->and(app(MaintenancePolicy::class)->state()['window_id'])->toBe($window->id)
        ->and(DB::getQueryLog())->toBeEmpty();
});

test('publisher clears state after the scheduled window is completed', function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-31 02:30:00Z'));
    $window = makeMaintenanceWindow();
    $publisher = app(MaintenanceStatePublisher::class);
    $publisher->sync();

    $window->update(['status' => MaintenanceWindowStatus::COMPLETED]);
    $publisher->sync();

    expect(app(MaintenancePolicy::class)->phase())->toBe(MaintenancePhase::NORMAL->value)
        ->and(app(MaintenanceStateStore::class)->read())->toBeNull();
});

test('publisher selects the next scheduled window after an expired one', function (): void {
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-31 03:30:00Z'));
    MaintenanceWindow::query()->create([
        'drain_starts_at' => '2026-08-31 01:30:00+00',
        'maintenance_starts_at' => '2026-08-31 02:00:00+00',
        'expected_end_at' => '2026-08-31 03:00:00+00',
        'status' => MaintenanceWindowStatus::STARTED,
    ]);
    $nextWindow = MaintenanceWindow::query()->create([
        'drain_starts_at' => '2026-08-31 04:00:00+00',
        'maintenance_starts_at' => '2026-08-31 04:30:00+00',
        'expected_end_at' => '2026-08-31 05:00:00+00',
    ]);

    app(MaintenanceStatePublisher::class)->sync();

    expect(app(MaintenanceStateStore::class)->read()['window_id'])->toBe($nextWindow->id)
        ->and(app(MaintenancePolicy::class)->phase())->toBe(MaintenancePhase::SCHEDULED->value);
});

test('publisher broadcasts only when the public maintenance state changes', function (): void {
    Event::fake([MaintenanceAvailabilityChanged::class]);
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-31 01:00:00Z'));
    makeMaintenanceWindow();
    $publisher = app(MaintenanceStatePublisher::class);

    $publisher->sync();
    $publisher->sync();

    Event::assertDispatchedTimes(MaintenanceAvailabilityChanged::class, 1);
});

test('publisher broadcasts normal state when maintenance state is cleared', function (): void {
    Event::fake([MaintenanceAvailabilityChanged::class]);
    CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-31 02:30:00Z'));
    $window = makeMaintenanceWindow();
    $publisher = app(MaintenanceStatePublisher::class);
    $publisher->sync();

    $window->update(['status' => MaintenanceWindowStatus::COMPLETED]);
    $publisher->sync();

    Event::assertDispatchedTimes(MaintenanceAvailabilityChanged::class, 2);
});

test('state store rejects malformed state and policy fails closed', function (): void {
    app(MaintenanceStateStore::class)->write(['version' => 1, 'mode' => MaintenancePhase::MAINTENANCE->value]);

    expect(app(MaintenancePolicy::class)->phase())->toBe(MaintenancePhase::MAINTENANCE->value);
    expect(app(MaintenancePolicy::class)->payload()['window'])->toBeNull();
});
