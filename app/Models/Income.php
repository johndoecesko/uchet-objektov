<?php

namespace App\Models;

use App\Enums\IncomeType;
use App\Enums\PayMethod;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\LogsChanges;
use App\Models\Concerns\TracksCreator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Income extends Model
{
    use HasAttachments, LogsChanges, SoftDeletes, TracksCreator;

    protected $fillable = ['date', 'project_id', 'amount', 'type', 'method', 'document', 'notes', 'attachments'];

    protected function casts(): array
    {
        return ['date' => 'date', 'attachments' => 'array', 'amount' => 'decimal:2', 'type' => IncomeType::class, 'method' => PayMethod::class];
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }
}
