<?php

namespace App\Support;

use Filament\Forms\Components\FileUpload;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * Фото профиля и логотип организации. Лежат на закрытом диске, как чеки; в браузер — подписанная ссылка.
 * Срок ссылки — до конца завтрашнего дня, поэтому в течение суток адрес один и тот же и картинка кэшируется.
 */
class Avatar
{
    public const DISK = 'local';

    public static function url(?string $path): ?string
    {
        if (blank($path)) {
            return null;
        }

        return Storage::disk(self::DISK)->temporaryUrl($path, now()->addDays(2)->startOfDay());
    }

    /** Поле загрузки: квадрат с обрезкой по кругу, уменьшение в браузере до 256 px */
    public static function field(string $name, string $directory, string $label): FileUpload
    {
        return FileUpload::make($name)
            ->label($label)
            ->disk(self::DISK)
            ->directory($directory)
            ->visibility('private')
            ->preventFilePathTampering()
            ->image()
            ->acceptedFileTypes(['image/jpeg', 'image/png', 'image/webp'])
            ->maxSize(5120)
            ->avatar()
            ->imageEditor()
            ->circleCropper()
            ->automaticallyCropImagesToAspectRatio('1:1')
            ->automaticallyResizeImagesToWidth('256')
            ->automaticallyResizeImagesToHeight('256');
    }

    /** Заменили или убрали картинку — старый файл удалить (вызывать из saving/deleted модели) */
    public static function forgetOld(Model $model, string $attribute, bool $deleted = false): void
    {
        $old = $deleted ? $model->getAttribute($attribute) : ($model->isDirty($attribute) ? $model->getOriginal($attribute) : null);
        if (filled($old)) {
            Storage::disk(self::DISK)->delete($old);
        }
    }
}
