<?php

namespace App\Filament\Pages;

use App\Models\Organization;
use App\Support\Demo\Demo;
use App\Support\Onboarding;
use Filament\Actions\Action;
use Filament\Facades\Filament;
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
            'checklistHidden' => $this->checklistHidden(),
        ];
    }

    /** Чек-лист «Первые шаги» скрыт участником (а не потому, что всё сделано) */
    private function checklistHidden(): bool
    {
        $org = Filament::getTenant();

        return ! Demo::active() && $org instanceof Organization
            && auth()->user()?->membershipIn($org)?->onboarding_hidden_at !== null;
    }

    /** «Показать чек-лист снова» — снять «Скрыть» у себя */
    public function showChecklistAction(): Action
    {
        return Action::make('showChecklist')
            ->label('Показать чек-лист снова')
            ->icon('heroicon-o-rocket-launch')
            ->color('gray')
            ->visible(fn () => $this->checklistHidden())
            ->action(function () {
                Onboarding::hide(auth()->user(), Filament::getTenant(), hidden: false);

                return redirect(Filament::getUrl(Filament::getTenant()));
            });
    }
}
