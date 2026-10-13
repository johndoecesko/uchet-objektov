<?php

namespace App\Models;

use App\Enums\AdvanceType;
use App\Enums\PayMethod;
use App\Models\Concerns\LogsChanges;
use App\Models\Concerns\TracksCreator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Advance extends Model
{
    use LogsChanges, SoftDeletes, TracksCreator;

    protected $fillable = ['date', 'employee_id', 'type', 'amount', 'method', 'notes'];

    protected function casts(): array
    {
        return ['date' => 'date', 'amount' => 'decimal:2', 'type' => AdvanceType::class, 'method' => PayMethod::class];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
