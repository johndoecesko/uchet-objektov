<?php

namespace App\Filament\Resources\ProjectResource\Pages;

use App\Enums\ProjectStatus;
use App\Filament\Resources\ProjectResource;
use App\Filament\Support\Fields;
use App\Reports\OwnerReport;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Carbon;

class ListProjects extends ListRecords
{
    protected static string $resource = ProjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('ownerReport')
                ->label('Отчёт собственнику')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn () => Fields::isOffice())
                ->modalHeading('Отчёт собственнику (Excel)')
                ->modalDescription('Строка на объект. Получено, расходы и зарплата — за период; договор, налог, маржа и прогноз — за весь срок.')
                ->modalSubmitActionLabel('Скачать')
                ->schema([
                    DatePicker::make('from')->label('С')->native(false)->displayFormat('d.m.Y')->required()
                        ->default(now()->startOfMonth()),
                    DatePicker::make('until')->label('По')->native(false)->displayFormat('d.m.Y')->required()
                        ->default(now())->afterOrEqual('from'),
                    Select::make('statuses')->label('Статусы объектов')->multiple()->options(ProjectStatus::class)
                        ->default([ProjectStatus::Planned->value, ProjectStatus::Active->value, ProjectStatus::Completed->value])
                        ->placeholder('Все'),
                ])
                ->action(function (array $data) {
                    abort_unless(Fields::isOffice(), 403);
                    $statuses = array_map(fn ($s) => $s instanceof ProjectStatus ? $s->value : (string) $s, $data['statuses'] ?? []);
                    $report = new OwnerReport(Carbon::parse($data['from']), Carbon::parse($data['until']), $statuses);

                    return $report->build()->download($report->filename());
                }),
            CreateAction::make(),
        ];
    }
}
