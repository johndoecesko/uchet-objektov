<?php

namespace App\Models\Concerns;

use Illuminate\Support\Facades\Storage;

/**
 * Вложения (фото чеков, платёжки) в поле attachments — массив путей на диске local.
 * При окончательном удалении записи из корзины файлы удаляются тоже.
 */
trait HasAttachments
{
    /** Папки, из которых разрешено удалять файлы этой модели */
    public const ATTACHMENT_DIRS = ['receipts/', 'documents/'];

    public static function bootHasAttachments(): void
    {
        static::forceDeleted(function ($model) {
            $files = array_filter((array) ($model->attachments ?? []), fn ($f) => is_string($f)
                && ! str_contains($f, '..')
                && array_filter(self::ATTACHMENT_DIRS, fn ($dir) => str_starts_with($f, $dir)));
            if ($files) {
                Storage::disk('local')->delete(array_values($files));
            }
        });
    }
}
