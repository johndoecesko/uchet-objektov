<?php

namespace App\Models;

use App\Enums\PaymentSource;
use App\Models\Concerns\LogsChanges;
use App\Models\Concerns\TracksCreator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Storage;

class Expense extends Model
{
    use LogsChanges, SoftDeletes, TracksCreator;

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

        // Окончательное удаление из корзины — убираем и файлы чеков
        static::forceDeleted(function (Expense $e) {
            $files = array_filter((array) ($e->attachments ?? []), fn ($f) => is_string($f) && str_starts_with($f, 'receipts/'));
            if ($files) {
                Storage::disk('local')->delete(array_values($files));
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
