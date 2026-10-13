<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\ActivityResource;
use App\Models\Employee;
use App\Models\Project;
use App\Models\ProjectBudget;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Spatie\Activitylog\Models\Activity;

/** История изменений одной записи (объекта — вместе с его планом по статьям) — внизу страницы просмотра */
class RecordHistory extends TableWidget
{
    public ?Model $record = null;

    protected int | string | array $columnSpan = 'full';

    protected static bool $isDiscovered = false;

    protected static bool $isLazy = true;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('История изменений')
            ->query(fn () => $this->historyQuery())
            ->columns(ActivityResource::tableColumns(withSubject: $this->record instanceof Project))
            ->defaultSort('id', 'desc')
            ->paginated([5, 10, 25])
            ->defaultPaginationPageOption(5);
    }

    private function historyQuery(): Builder
    {
        $r = $this->record;

        return Activity::query()
            ->with(['causer', 'subject'])
            ->where(function (Builder $q) use ($r) {
                $q->where(fn (Builder $q) => $q->where('subject_type', $r::class)->where('subject_id', $r->getKey()));
                if ($r instanceof Project) {
                    $q->orWhere(fn (Builder $q) => $q->where('subject_type', ProjectBudget::class)->where('properties->project_id', $r->getKey()));
                }
            });
    }
}
