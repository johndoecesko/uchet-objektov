<?php

namespace App\Support;

use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Выгрузка резервных копий наружу по WebDAV (Nextcloud).
 * Рекомендуемый вариант — публичная ссылка на папку «только загрузка» (File drop) с паролем:
 * хостинг может класть файлы, но не может читать и удалять остальное содержимое облака.
 */
class BackupOffsite
{
    public static function enabled(): bool
    {
        return filled(config('backup.webdav.url'));
    }

    /** Файл-метка последней успешной выгрузки (время — по дате изменения) */
    public static function marker(): string
    {
        return storage_path('framework/backup-offsite-ok');
    }

    /** Сколько дней назад была успешная выгрузка (null — ни разу) */
    public static function daysSinceLast(): ?int
    {
        $file = self::marker();

        return is_file($file) ? (int) floor((time() - filemtime($file)) / 86400) : null;
    }

    public function upload(string $path): void
    {
        $url = rtrim((string) config('backup.webdav.url'), '/').'/'.rawurlencode(basename($path));
        $stream = fopen($path, 'rb');
        if (! $stream) {
            throw new RuntimeException('Не открыть '.basename($path));
        }

        try {
            $response = Http::withBasicAuth((string) config('backup.webdav.user'), (string) config('backup.webdav.password'))
                ->withHeaders(['X-Requested-With' => 'XMLHttpRequest'])
                ->withBody($stream, 'application/octet-stream')
                ->timeout(600)
                ->put($url);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        // в сообщении — только код ответа: ни адреса с токеном, ни пароля
        if (! $response->successful()) {
            throw new RuntimeException('WebDAV ответил '.$response->status().' на '.basename($path));
        }
    }

    public function markSuccess(): void
    {
        touch(self::marker());
    }
}
