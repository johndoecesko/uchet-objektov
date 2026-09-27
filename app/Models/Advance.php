<?php

namespace App\Models;

use App\Enums\AdvanceType;
use App\Enums\PayMethod;
use App\Models\Concerns\TracksCreator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Advance extends Model
{
    use TracksCreator;

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
