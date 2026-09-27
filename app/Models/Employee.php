<?php

namespace App\Models;

use App\Enums\AdvanceType;
use App\Enums\PaymentSource;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Employee extends Model
{
    protected $fillable = ['name', 'phone', 'position', 'day_rate', 'card_percent', 'is_active', 'user_id', 'notes'];

    protected $attributes = ['is_active' => true, 'day_rate' => 0, 'card_percent' => 0];

    protected function casts(): array
    {
        return ['day_rate' => 'decimal:2', 'card_percent' => 'integer', 'is_active' => 'boolean'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function workLogs(): HasMany
    {
        return $this->hasMany(WorkLog::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }

    public function advances(): HasMany
    {
        return $this->hasMany(Advance::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    /** Расходы, оплаченные сотрудником из подотчётных денег */
    public function advanceExpenses(): HasMany
    {
        return $this->hasMany(Expense::class)->where('payment_source', PaymentSource::Advance->value);
    }

    public function accrued(): float
    {
        return (float) $this->workLogs()->sum('amount');
    }

    public function paid(): float
    {
        return (float) $this->payouts()->sum('amount');
    }

    /** Долг компании перед сотрудником по зарплате (+) или переплата (−) */
    public function salaryDebt(): float
    {
        return $this->accrued() - $this->paid();
    }

    /** Остаток подотчёта: выдано − возвращено − подтверждено чеками. (+) числится за сотрудником */
    public function advanceBalance(): float
    {
        $issued = (float) $this->advances()->where('type', AdvanceType::Issue->value)->sum('amount');
        $returned = (float) $this->advances()->where('type', AdvanceType::Return->value)->sum('amount');
        $spent = (float) $this->advanceExpenses()->sum('amount');

        return $issued - $returned - $spent;
    }
}
