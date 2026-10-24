<?php

namespace App\Filament;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Filament\Facades\Filament;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

/**
 * Инициалы вместо фото — картинкой прямо в странице (SVG в data:-ссылке).
 * Стандартный поставщик Filament ходит за ними на ui-avatars.com: лишний внешний запрос с IP пользователя за границу.
 */
class InitialsAvatarProvider implements AvatarProvider
{
    public function get(Model|Authenticatable $record): string
    {
        $initials = collect(explode(' ', trim((string) Filament::getNameForDefaultAvatar($record))))
            ->map(fn (string $word) => mb_substr(preg_replace('/^[^\p{L}\p{N}]+/u', '', $word), 0, 1))
            ->filter()
            ->take(2)
            ->implode('');

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64">'
            .'<rect width="64" height="64" fill="#030712"/>'
            .'<text x="32" y="32" dy=".35em" text-anchor="middle" fill="#fff" font-family="system-ui, sans-serif" font-size="26" font-weight="600">'
            .e(mb_strtoupper($initials))
            .'</text></svg>';

        return 'data:image/svg+xml;base64,'.base64_encode($svg);
    }
}
