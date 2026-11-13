<?php

namespace App\Filament\Actions;

use App\Filament\Resources\ProjectResource;
use App\Models\Organization;
use App\Models\Project;
use App\Support\Demo\Demo;
use App\Support\Demo\ExampleData;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;

/**
 * «Заполнить примером» и «Удалить пример» — в чек-листе «Первые шаги» и в пустом списке объектов.
 * Заполнить — пока своих объектов нет и примера нет; удалить — пока пример есть. Права — политика объектов.
 */
class ExampleActions
{
    public static function fill(string $name = 'fillExample'): Action
    {
        return Action::make($name)
            ->label('Заполнить примером')
            ->icon('heroicon-o-sparkles')
            ->color('gray')
            ->authorize(fn () => (bool) auth()->user()?->can('create', Project::class))
            ->visible(fn () => ! Demo::active() && ! ExampleData::exists() && ! Project::query()->real()->exists())
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-sparkles')
            ->modalHeading('Заполнить примером?')
            ->modalDescription('Добавим объект «Пример — ЖК «Солнечный»» с бюджетом, расходами, журналом работ двух сотрудников и оплатами заказчика — чтобы посмотреть, как считаются план/факт и прибыль. Пример помечен и удаляется одной кнопкой «Удалить пример».')
            ->modalSubmitActionLabel('Заполнить')
            ->action(function () {
                abort_unless(auth()->user()?->can('create', Project::class), 403);
                $project = ExampleData::add(self::tenant());
                Notification::make()->title('Пример добавлен')->body('Это объект «Пример — …». Удалить — на главной, «Удалить пример».')->success()->send();

                return redirect(ProjectResource::getUrl('view', ['record' => $project]));
            });
    }

    public static function remove(string $name = 'removeExample'): Action
    {
        return Action::make($name)
            ->label('Удалить пример')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->authorize(fn () => (bool) auth()->user()?->can('deleteAny', Project::class))
            ->visible(fn () => ! Demo::active() && ExampleData::exists())
            ->requiresConfirmation()
            ->modalHeading('Удалить пример?')
            ->modalDescription(function () {
                $s = ExampleData::summary();

                return 'Удалится сразу и окончательно (без корзины): объект-пример ('.$s['projects'].'), сотрудники-примеры ('.$s['employees'].'), '
                    .'а также всё, что внесено в объект-пример, — в том числе вами: расходов '.$s['expenses'].', записей журнала '.$s['work_logs'].', поступлений '.$s['incomes'].'. '
                    .'Сотрудник из примера, которого вы уже поставили на свой объект, останется — без пометки «Пример».';
            })
            ->modalSubmitActionLabel('Удалить пример')
            ->action(function () {
                abort_unless(auth()->user()?->can('deleteAny', Project::class), 403);
                $result = ExampleData::remove(self::tenant());
                Notification::make()->title('Пример удалён')
                    ->body($result['kept'] ? 'Остались сотрудники (уже работают на ваших объектах): '.implode(', ', $result['kept']).'.' : null)
                    ->success()->send();

                return redirect(Filament::getUrl(self::tenant()));
            });
    }

    private static function tenant(): Organization
    {
        $tenant = Filament::getTenant();
        abort_unless($tenant instanceof Organization, 404);

        return $tenant;
    }
}
