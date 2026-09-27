<?php

namespace App\Models;

use App\Enums\PaymentSource;
use App\Models\Concerns\TracksCreator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Expense extends Model
{
    use TracksCreator;

    protected $fillable = [
        'date', 'project_id', 'expense_category_id', 'amount', 'supplier', 'document',
        'payment_source', 'employee_id', 'description', 'attachments',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'amount' => 'decimal:2',
            'payment_source' => PaymentSource::class,
            'attachments' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (Expense $e) {
            if ($e->payment_source !== PaymentSource::Advance) {
                $e->employee_id = null;
            }
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(ExpenseCategory::class, 'expense_category_id');
    }

    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }
}
