<?php

namespace App\Filament\Resources\EmployeeResource\Pages;

use App\Filament\Resources\EmployeeResource;
use App\Filament\Resources\EmployeeResource\Widgets\EmployeeStats;
use App\Filament\Support\Fields;
use App\Filament\Widgets\RecordHistory;
use App\Reports\PayslipReport;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Support\Carbon;

class ViewEmployee extends ViewRecord
{
    protected static string $resource = EmployeeResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->name;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('payslip')
                ->label('Расчётный листок')
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->visible(fn () => Fields::isOffice())
                ->modalHeading('Расчётный листок (Excel)')
                ->modalSubmitActionLabel('Скачать')
                ->schema([
                    DatePicker::make('from')->label('С')->native(false)->displayFormat('d.m.Y')->required()
                        ->default(now()->startOfMonth()),
                    DatePicker::make('until')->label('По')->native(false)->displayFormat('d.m.Y')->required()
                        ->default(now()->endOfMonth())->afterOrEqual('from'),
                ])
                ->action(function (array $data) {
                    abort_unless(Fields::isOffice(), 403);
                    $report = new PayslipReport($this->getRecord(), Carbon::parse($data['from']), Carbon::parse($data['until']));

                    return $report->build()->download($report->filename());
                }),
            EditAction::make(),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [EmployeeStats::class];
    }

    protected function getFooterWidgets(): array
    {
        return [RecordHistory::class];
    }
}
