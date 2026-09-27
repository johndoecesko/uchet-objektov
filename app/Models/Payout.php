<?php

namespace App\Models;

use App\Enums\PayMethod;
use App\Models\Concerns\TracksCreator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Payout extends Model
{
    use TracksCreator;

    protected $fillable = ['date', 'employee_id', 'project_id', 'amount', 'method', 'notes'];

    protected function casts(): array
    {
        return ['date' => 'date', 'amount' => 'decimal:2', 'method' => PayMethod::class];
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
