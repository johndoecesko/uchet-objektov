<?php

namespace App\Models;

use App\Enums\IncomeType;
use App\Enums\PayMethod;
use App\Models\Concerns\TracksCreator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Income extends Model
{
    use TracksCreator;

    protected $fillable = ['date', 'project_id', 'amount', 'type', 'method', 'document', 'notes'];

    protected function casts(): array
    {
        return ['date' => 'date', 'amount' => 'decimal:2', 'type' => IncomeType::class, 'method' => PayMethod::class];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
