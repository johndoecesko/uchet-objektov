<?php

// Резервные копии: php artisan app:backup (по расписанию — см. routes/console.php)
return [

    // Куда складывать. По умолчанию ~/backups/uchet (на хостинге — рядом с бэкапами других сайтов).
    'path' => env('BACKUP_PATH', (getenv('HOME') ?: storage_path('app')).'/backups/uchet'),

    // Сколько дней хранить все копии, затем — по одной на неделю до keep_weeks недель
    'keep_days' => (int) env('BACKUP_KEEP_DAYS', 14),
    'keep_weeks' => (int) env('BACKUP_KEEP_WEEKS', 8),

    // Копия наружу по WebDAV (Nextcloud). Пусто — не выгружать.
    // File drop: url = https://<nextcloud>/public.php/webdav, user = токен из ссылки, password = пароль ссылки.
    'webdav' => [
        'url' => env('BACKUP_WEBDAV_URL'),
        'user' => env('BACKUP_WEBDAV_USER'),
        'password' => env('BACKUP_WEBDAV_PASSWORD'),
    ],

    'mysqldump' => env('BACKUP_MYSQLDUMP', 'mysqldump'),
    'tar' => env('BACKUP_TAR', 'tar'),

];
