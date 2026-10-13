<?php

namespace App\Models\Concerns;

use Spatie\Activitylog\Contracts\Activity;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

/**
 * История изменений (spatie/laravel-activitylog): кто, когда и что поменял.
 * Пишутся только заполняемые поля и только изменившиеся; пустые записи не создаются.
 */
trait LogsChanges
{
    use LogsActivity;

    /** Поля, которые не попадают в историю (пароли и т. п.) */
    protected function activityExcept(): array
    {
        return [];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logFillable()
            ->logExcept($this->activityExcept())
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function tapActivity(Activity $activity, string $eventName): void
    {
        // Удаление с корзиной: «в корзину» и «удалено навсегда» различаем по событию
        if ($eventName === 'deleted' && method_exists($this, 'isForceDeleting')) {
            $activity->event = $this->isForceDeleting() ? 'forceDeleted' : 'trashed';
        }
    }
}
