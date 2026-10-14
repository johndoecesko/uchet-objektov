<?php

namespace App\Models;

use App\Enums\PayMethod;
use App\Models\Concerns\HasAttachments;
use App\Models\Concerns\LogsChanges;
use App\Models\Concerns\TracksCreator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payout extends Model
{
    use HasAttachments, LogsChanges, SoftDeletes, TracksCreator;

    protected $fillable = ['date', 'employee_id', 'project_id', 'amount', 'method', 'notes', 'attachments'];

    protected function casts(): array
    {
        return ['date' => 'date', 'attachments' => 'array', 'amount' => 'decimal:2', 'method' => PayMethod::class];
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
