<?php

namespace App\Reports;

use App\Enums\ProjectStatus;
use App\Models\Project;
use App\Support\Xlsx;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * Отчёт собственнику за период: строка на объект.
 * Получено, расходы и зарплата — за период; договор, налог, затраты всего, маржа и прогноз — за весь срок объекта.
 */
class OwnerReport
{
    /** @param list<string> $statuses */
    public function __construct(private Carbon $from, private Carbon $until, private array $statuses = []) {}

    public function filename(): string
    {
        return 'sobstvenniku-'.$this->from->format('Y-m-d').'-'.$this->until->format('Y-m-d').'.xlsx';
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, Project> */
    public function projects()
    {
        $period = fn (Builder $q) => $q->whereDate('date', '>=', $this->from)->whereDate('date', '<=', $this->until);

        return Project::query()
            ->when($this->statuses, fn (Builder $q) => $q->whereIn('status', $this->statuses))
            ->withSum(['incomes as period_received' => $period], 'amount')
            ->withSum(['expenses as period_expenses' => $period], 'amount')
            ->withSum(['workLogs as period_labor' => $period], 'amount')
            ->withSum('expenses', 'amount')
            ->withSum('workLogs', 'amount')
            ->withSum('budgets', 'amount')
            ->orderBy('name')
            ->get();
    }

    public function build(): Xlsx
    {
        $m = Xlsx::MONEY;
        $formats = [null, null, null, $m, $m, $m, $m, $m, $m, $m, $m, Xlsx::PERCENT];
        $x = new Xlsx;
        $x->sheet('Объекты', [
            'Объект' => [32], 'Заказчик' => [26], 'Статус' => [12], 'Договор' => [15],
            'Получено за период' => [15], 'Расходы за период' => [15], 'Зарплата за период' => [15],
            'Налог' => [13], 'Затраты всего' => [15], 'Маржа по договору' => [15], 'Прогноз прибыли' => [15], 'Освоение бюджета' => [11],
        ]);

        $t = array_fill(0, 12, 0.0);
        foreach ($this->projects() as $p) {
            $tax = $p->taxTotal();
            $cost = (float) $p->expenses_sum_amount + (float) $p->work_logs_sum_amount + $tax;
            $budget = (float) $p->budgets_sum_amount;
            $row = [
                $p->name, $p->customer, $p->status?->getLabel(), (float) $p->contract_amount,
                (float) $p->period_received, (float) $p->period_expenses, (float) $p->period_labor,
                $tax, $cost, (float) $p->contract_amount - $cost, $p->forecastProfit(),
                $budget > 0 ? $cost / $budget : null,
            ];
            $x->row($row, $formats);
            foreach ([3, 4, 5, 6, 7, 8, 9, 10] as $i) {
                $t[$i] += $row[$i];
            }
            $t[11] += $budget;
        }
        $budgetTotal = $t[11];
        $t[11] = $budgetTotal > 0 ? $t[8] / $budgetTotal : null;
        $t[0] = 'Итого';
        $t[1] = $t[2] = null;
        $x->row($t, $formats, bold: true);

        $x->blank()
            ->row(['Период', $this->from->format('d.m.Y').' — '.$this->until->format('d.m.Y')])
            ->row(['Статусы', $this->statuses ? collect($this->statuses)->map(fn ($s) => ProjectStatus::from($s)->getLabel())->implode(', ') : 'все'])
            ->row(['Примечание', 'Получено, расходы и зарплата — за период; договор, налог, затраты, маржа и прогноз — за весь срок объекта'])
            ->row(['Сформировано', now()->format('d.m.Y H:i')]);

        return $x;
    }
}
