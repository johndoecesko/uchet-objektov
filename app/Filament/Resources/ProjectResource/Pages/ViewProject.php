<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Filament\Resources\ExpenseResource;
use App\Filament\Resources\IncomeResource;
use App\Filament\Resources\ProjectResource;
use App\Filament\Resources\ProjectResource\Widgets\ProjectPlanFact;
use App\Filament\Resources\ProjectResource\Widgets\ProjectStats;
use App\Filament\Resources\WorkLogResource;
use App\Filament\Support\Fields;
use App\Support\Demo\Demo;
use App\Filament\Widgets\RecordHistory;
use App\Models\Expense;
use App\Models\Income;
use App\Models\WorkLog;
use App\Reports\ProjectReport;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewProject extends ViewRecord
{
    protected static string $resource = ProjectResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->name;
    }

    /**
     * Кнопка «добавить» в шапке объекта: форма выезжает справа, объект подставляется сам.
     * Права — по создаваемой модели: без authorize() Filament проверил бы ProjectResource::canCreate
     * (страница принадлежит ресурсу объектов), и прораб не увидел бы кнопку «Расход».
     */
    protected function quickAdd(string $name, string $label, string $icon, string $model, array $fields, string $relation): CreateAction
    {
        return CreateAction::make($name)
            ->label($label)
            ->icon($icon)
            ->model($model)
            ->authorize(fn () => auth()->user()?->can('create', $model) ?? false)
            ->relationship(fn () => $this->getRecord()->{$relation}())
            ->schema($fields)
            ->slideOver()
            ->modalHeading($label.' — '.$this->getRecord()->name)
            ->createAnother(false)
            ->successRedirectUrl(fn () => ProjectResource::getUrl('view', ['record' => $this->getRecord()]));
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->quickAdd('addExpense', 'Расход', 'heroicon-o-plus', Expense::class, ExpenseResource::formFields(withProject: false), 'expenses'),
            $this->quickAdd('addWork', 'Работа', 'heroicon-o-plus', WorkLog::class, WorkLogResource::formFields(withProject: false), 'workLogs')
                ->color('gray'),
            $this->quickAdd('addIncome', 'Поступление', 'heroicon-o-plus', Income::class, IncomeResource::formFields(withProject: false), 'incomes')
                ->color('gray'),
            Action::make('exportXlsx')
                ->label('Excel')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->tooltip('Сводка, план/факт, расходы, работы и поступления за весь срок')
                ->visible(fn () => Fields::isOffice())
                ->action(function () {
                    abort_unless(Fields::isOffice(), 403);
                    Demo::guardExport(); // в демо — не больше 10 в час
                    $report = new ProjectReport($this->getRecord());

                    return $report->build()->download($report->filename());
                }),
            EditAction::make()->color('gray'),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [ProjectStats::class, ProjectPlanFact::class];
    }

    public function getHeaderWidgetsColumns(): int | array
    {
        return 1;
    }

    protected function getFooterWidgets(): array
    {
        return [RecordHistory::class];
    }
}
