<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Backup Storage Disks & Destinations
    |--------------------------------------------------------------------------
    |
    | By default, database backups are stored in storage/app/backups.
    | A secondary destination (e.g. an external disk outside webroot, an
    | S3-compatible bucket, or Google Drive disk) can be configured here.
    | Set to null to disable.
    |
    */
    'secondary_disk' => env('BACKUP_SECONDARY_DISK', null),

    /*
    |--------------------------------------------------------------------------
    | Backup Encryption
    |--------------------------------------------------------------------------
    |
    | When enabled, generated SQL dumps are encrypted with the application key
    | (APP_KEY) using Laravel's encrypter (AES-256-CBC) before being saved.
    | Disabled by default.
    |
    */
    'encryption_enabled' => (bool) env('BACKUP_ENCRYPTION_ENABLED', false),
];
