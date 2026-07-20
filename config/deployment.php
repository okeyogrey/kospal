<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Deployment mode
    |--------------------------------------------------------------------------
    |
    | desktop (default): local-first SQLite / Tauri packaging.
    | web: legacy SaaS adapters (platform billing, subscription expiry).
    |
    */

    'mode' => env('KOSPAL_DEPLOYMENT_MODE', 'desktop'),

    'version' => env('KOSPAL_APP_VERSION', '0.1.0'),

    /*
    |--------------------------------------------------------------------------
    | Local license defaults (desktop)
    |--------------------------------------------------------------------------
    */

    'license' => [
        // Plan enum value used as the trial / licensed edition on first onboarding.
        'default_edition' => env('KOSPAL_LICENSE_EDITION', 'enterprise'),
        'trial_days' => (int) env('KOSPAL_LICENSE_TRIAL_DAYS', 30),
        // HMAC secret for signed license keys. Falls back to APP_KEY when empty.
        'secret' => env('KOSPAL_LICENSE_SECRET'),
        // Optional remote activation endpoint. When empty, online keys are verified locally.
        'server_url' => env('KOSPAL_LICENSE_SERVER_URL'),
        // Override path for the installation machine id file (tests).
        'machine_id_path' => env('KOSPAL_MACHINE_ID_PATH'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Desktop / local settings defaults
    |--------------------------------------------------------------------------
    */

    'settings_path' => env('KOSPAL_SETTINGS_PATH'),

    'settings' => [
        'data_directory' => env('KOSPAL_DATA_DIRECTORY'),
        'printer_name' => env('KOSPAL_PRINTER_NAME'),
        'receipt_width' => env('KOSPAL_RECEIPT_WIDTH', '80'),
        'update_channel' => env('KOSPAL_UPDATE_CHANNEL', 'stable'),
        'auto_backup' => (bool) env('KOSPAL_AUTO_BACKUP', false),
        'auto_backup_hour' => (int) env('KOSPAL_AUTO_BACKUP_HOUR', 2),
    ],

    /*
    |--------------------------------------------------------------------------
    | Desktop update feed (optional)
    |--------------------------------------------------------------------------
    |
    | JSON feed may return { "version": "1.2.0", "notes": "..." } or
    | { "channels": { "stable": { "version": "1.2.0" }, "beta": "1.3.0-beta" } }.
    |
    */

    'updates' => [
        'feed_url' => env('KOSPAL_UPDATE_FEED_URL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Database (desktop SQLite default; MySQL/pgsql portable)
    |--------------------------------------------------------------------------
    |
    | schema_version is the application schema watermark recorded in
    | database_versions after migrations. Bump when shipping meaningful
    | schema releases. Existing Laravel migration files are never rewritten.
    |
    */

    'database' => [
        'schema_version' => 2,
        'default_driver' => 'sqlite',
        'supported_drivers' => ['sqlite', 'mysql', 'mariadb', 'pgsql'],
        'sqlite' => [
            'journal_mode' => env('KOSPAL_SQLITE_JOURNAL_MODE', 'WAL'),
            'synchronous' => env('KOSPAL_SQLITE_SYNCHRONOUS', 'NORMAL'),
            'busy_timeout' => (int) env('KOSPAL_SQLITE_BUSY_TIMEOUT', 5000),
            'foreign_keys' => (bool) env('DB_FOREIGN_KEYS', true),
        ],
    ],

];
