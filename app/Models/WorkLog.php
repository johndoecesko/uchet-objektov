<?php

namespace App\Models;

use App\Models\Concerns\TracksCreator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WorkLog extends Model
{
    use TracksCreator;

    protected $fillable = ['date', 'project_id', 'employee_id', 'work', 'days', 'rate', 'amount', 'notes'];

    protected function casts(): array
    {
        return ['date' => 'date', 'days' => 'decimal:2', 'rate' => 'decimal:2', 'amount' => 'decimal:2'];
    }

    protected static function booted(): void
    {
        static::saving(function (WorkLog $w) {
            // Если сумму не задали вручную — считаем дни × ставка
            if ($w->amount === null || (float) $w->amount === 0.0) {
                $w->amount = round((float) $w->days * (float) $w->rate, 2);
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
