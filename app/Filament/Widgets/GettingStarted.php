<?php

namespace App\Filament\Widgets;

use App\Filament\Actions\ConnectMaxAction;
use App\Filament\Actions\ExampleActions;
use App\Models\Organization;
use App\Models\User;
use App\Support\Demo\Demo;
use App\Support\Onboarding;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Facades\Filament;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Widgets\Widget;

/**
 * «Первые шаги» на главной: чек-лист по реальному состоянию организации, «Заполнить примером» / «Удалить пример»,
 * «Скрыть» (у себя). В демо не показывается. Всё сделано или скрыто — не виден.
 */
class GettingStarted extends Widget implements HasActions, HasSchemas
{
    use InteractsWithActions;
    use InteractsWithSchemas;

    protected string $view = 'filament.widgets.getting-started';

    protected static ?int $sort = -1;

    protected static bool $isLazy = false;

    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        $user = auth()->user();
        $org = Filament::getTenant();

        return $user instanceof User && $org instanceof Organization && ! Demo::active() && ! Onboarding::hidden($user, $org);
    }

    /** @return list<array<string, mixed>> */
    public function getSteps(): array
    {
        return Onboarding::steps(auth()->user(), Filament::getTenant());
    }

    public function hideAction(): Action
    {
        return Action::make('hide')
            ->label('Скрыть')
            ->link()
            ->color('gray')
            ->tooltip('Вернуть — «О программе и помощь» → «Показать чек-лист снова»')
            ->action(function () {
                Onboarding::hide(auth()->user(), Filament::getTenant());

                return redirect(Filament::getUrl(Filament::getTenant()));
            });
    }

    public function fillExampleAction(): Action
    {
        return ExampleActions::fill();
    }

    public function removeExampleAction(): Action
    {
        return ExampleActions::remove();
    }

    public function connectMaxAction(): Action
    {
        return ConnectMaxAction::make('connectMax')->button()->size('sm')->color('gray');
    }
}
