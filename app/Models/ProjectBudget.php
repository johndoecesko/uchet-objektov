<?php

namespace App\Models;

use App\Models\Concerns\LogsChanges;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Contracts\Activity;

class ProjectBudget extends Model
{
    use LogsChanges;

    protected $fillable = ['project_id', 'expense_category_id', 'amount'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    /** Объект в каждой записи истории — чтобы показать изменения плана на странице объекта */
    public function tapActivity(Activity $activity, string $eventName): void
    {
        $activity->properties = $activity->properties->put('project_id', $this->project_id);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }
}
