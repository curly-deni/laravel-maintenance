<?php

// Configuration for curly-deni/laravel-maintenance.
return [
    'table' => 'operations__maintenance_windows',
    'lock_table' => 'operations__maintenance_window_locks',
    'state_path' => storage_path('app/maintenance/state.json'),
    'flag_path' => storage_path('app/maintenance/maintenance.flag'),
];
