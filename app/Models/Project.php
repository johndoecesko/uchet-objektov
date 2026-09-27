<?php

namespace App\Models;

use App\Enums\ProjectStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class Project extends Model
{
    protected $fillable = [
        'name', 'customer', 'customer_inn', 'customer_kpp', 'customer_ogrn', 'customer_address', 'address', 'contract_number', 'contract_date', 'contract_amount', 'tax_percent',
        'status', 'start_date', 'end_date', 'notes',
    ];

    protected function casts(): array
    {
        return [
            'status' => ProjectStatus::class,
            'contract_amount' => 'decimal:2',
            'tax_percent' => 'decimal:2',
            'contract_date' => 'date',
            'start_date' => 'date',
            'end_date' => 'date',
        ];
    }

    public function budgets(): HasMany
    {
        return $this->hasMany(ProjectBudget::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function workLogs(): HasMany
    {
        return $this->hasMany(WorkLog::class);
    }

    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }

    public function expensesTotal(): float
    {
        return (float) $this->expenses()->sum('amount');
    }

    public function laborTotal(): float
    {
        return (float) $this->workLogs()->sum('amount');
    }

    /** Налоги: процент от суммы договора (считаются сами, вручную не вносятся) */
    public function taxTotal(): float
    {
        return static::taxFor((float) $this->contract_amount, (float) $this->tax_percent);
    }

    public static function taxFor(float $contract, float $percent): float
    {
        return round($contract * $percent / 100, 2);
    }

    public function costTotal(): float
    {
        return $this->expensesTotal() + $this->laborTotal() + $this->taxTotal();
    }

    public function receivedTotal(): float
    {
        return (float) $this->incomes()->sum('amount');
    }

    public function budgetTotal(): float
    {
        return (float) $this->budgets()->sum('amount');
    }

    /** Маржа по договору: сумма договора − фактические затраты */
    public function contractMargin(): float
    {
        return (float) $this->contract_amount - $this->costTotal();
    }

    /** Денежный результат: получено от заказчика − фактические затраты */
    public function cashResult(): float
    {
        return $this->receivedTotal() - $this->costTotal();
    }

    /**
     * Ожидаемые затраты: по каждой статье большее из плана и факта
     * (статья без плана — по факту, перерасход — тоже по факту).
     */
    public function forecastCost(): float
    {
        return (float) $this->planFact()->sum(fn (array $r) => max($r['plan'], $r['fact']));
    }

    /** Прогноз прибыли: договор − ожидаемые затраты */
    public function forecastProfit(): float
    {
        return (float) $this->contract_amount - $this->forecastCost();
    }

    public function customerDebt(): float
    {
        return (float) $this->contract_amount - $this->receivedTotal();
    }

    /**
     * План/факт по статьям затрат.
     * Начисления из журнала работ попадают в статью с флагом is_labor.
     *
     * @return Collection<int, array{category_id:int, name:string, plan:float, fact:float, rest:float}>
     */
    public function planFact(): Collection
    {
        $plan = $this->budgets()->pluck('amount', 'expense_category_id')->map(fn ($v) => (float) $v);
        $fact = $this->expenses()
            ->selectRaw('expense_category_id, SUM(amount) as total')
            ->groupBy('expense_category_id')
            ->pluck('total', 'expense_category_id')
            ->map(fn ($v) => (float) $v);

        $laborId = ExpenseCategory::laborId();
        if ($laborId) {
            $fact[$laborId] = ($fact[$laborId] ?? 0) + $this->laborTotal();
        }

        // Налоги: факт = процент от договора; если плана нет — план равен расчёту
        $taxId = ExpenseCategory::taxId();
        if ($taxId && $this->taxTotal() > 0) {
            $fact[$taxId] = ($fact[$taxId] ?? 0) + $this->taxTotal();
            if (! isset($plan[$taxId])) {
                $plan[$taxId] = $this->taxTotal();
            }
        }

        $ids = $plan->keys()->merge($fact->keys())->unique();

        return ExpenseCategory::query()
            ->whereIn('id', $ids)
            ->orderBy('sort')->orderBy('name')
            ->get()
            ->map(function (ExpenseCategory $c) use ($plan, $fact) {
                $p = $plan[$c->id] ?? 0.0;
                $f = $fact[$c->id] ?? 0.0;

                return ['category_id' => $c->id, 'name' => $c->name, 'plan' => $p, 'fact' => $f, 'rest' => $p - $f, 'is_tax' => (bool) $c->is_tax];
            })
            ->values();
    }
}
