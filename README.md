# Laravel Maintenance

`curly-deni/laravel-maintenance` provides scheduled maintenance windows, a file-backed runtime state, middleware for draining new work, and Artisan commands for operating maintenance windows.

## Installation

```bash
composer require curly-deni/laravel-maintenance
php artisan vendor:publish --tag=laravel-maintenance-migrations
php artisan migrate
```

The migration creates `operations__maintenance_windows` and an internal lock table used to serialize window saves. The optional `created_by` column is an unsigned ID without a foreign key so the package does not depend on an application's user table.

Publish the configuration to customize the maintenance table name and runtime state locations. The `table` setting is used by both the Eloquent model and the migration stub; set it before running the migration and keep it (along with `lock_table`) unchanged for rollbacks.

```bash
php artisan vendor:publish --tag=laravel-maintenance-config
```

## Usage

The package registers these commands:

```bash
php artisan maintenance:status
php artisan maintenance:status --json
php artisan maintenance:sync-state
php artisan maintenance:cancel {window}
php artisan maintenance:complete {window}
```

Apply the `maintenance.new-work` middleware alias to routes that should reject new operations while a window is draining. The package updates the runtime state file when `maintenance:sync-state` runs and broadcasts `Aesis\Maintenance\Events\MaintenanceAvailabilityChanged` when the public state changes.

The current runtime state can be read without querying the database:

```php
use Aesis\Maintenance\Facades\Maintenance;

$phase = Maintenance::phase();
$state = Maintenance::payload();
```

## Development

```bash
composer test
composer analyse
composer format
```

## License

MIT. See [LICENSE.md](LICENSE.md).
