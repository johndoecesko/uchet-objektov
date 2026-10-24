<?php

namespace App\Filament\Pages;

use Filament\Pages\Page;
use Illuminate\Support\Carbon;

/** О программе и помощь: версия, что нового, как начать, частые вопросы, контакты. Открывается из меню пользователя */
class About extends Page
{
    protected static ?string $title = 'О программе и помощь';

    protected static ?string $slug = 'about';

    protected static bool $shouldRegisterNavigation = false;

    protected string $view = 'filament.pages.about';

    public static function version(): string
    {
        return config('releases.0.version');
    }

    public static function releasedAt(): string
    {
        return Carbon::parse(config('releases.0.date'))->format('d.m.Y');
    }

    protected function getViewData(): array
    {
        return [
            'releases' => collect(config('releases'))->map(fn (array $r) => [...$r, 'date' => Carbon::parse($r['date'])->format('d.m.Y')]),
            'isAdmin' => (bool) auth()->user()?->isAdmin(),
        ];
    }
}
