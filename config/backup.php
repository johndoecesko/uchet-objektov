<?php

// Резервные копии: php artisan app:backup (по расписанию — см. routes/console.php)
return [

    // Куда складывать. По умолчанию ~/backups/uchet (на хостинге — рядом с бэкапами других сайтов).
    'path' => env('BACKUP_PATH', (getenv('HOME') ?: storage_path('app')).'/backups/uchet'),

    // Сколько дней хранить все копии, затем — по одной на неделю до keep_weeks недель
    'keep_days' => (int) env('BACKUP_KEEP_DAYS', 14),
    'keep_weeks' => (int) env('BACKUP_KEEP_WEEKS', 8),

    'mysqldump' => env('BACKUP_MYSQLDUMP', 'mysqldump'),
    'tar' => env('BACKUP_TAR', 'tar'),

];
