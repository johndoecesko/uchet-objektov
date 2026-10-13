<?php

namespace App\Reports;

use App\Enums\AdvanceType;
use App\Enums\PaymentSource;
use App\Models\Employee;
use App\Support\Xlsx;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/** Расчётный листок сотрудника за период: начисления по дням/объектам, выплаты, подотчёт, остатки */
class PayslipReport
{
    public function __construct(private Employee $employee, private Carbon $from, private Carbon $until) {}

    public function filename(): string
    {
        return 'listok-'.$this->employee->id.'-'.$this->from->format('Y-m-d').'-'.$this->until->format('Y-m-d').'.xlsx';
    }

    private function in(HasMany $q): HasMany
    {
        return $q->whereDate('date', '>=', $this->from)->whereDate('date', '<=', $this->until);
    }

    private function before(HasMany $q, Carbon $date): HasMany
    {
        return $q->whereDate('date', '<', $date);
    }

    /** Остатки на начало дня $date: [долг по зарплате, подотчёт на руках] */
    public function balancesAt(Carbon $date): array
    {
        $e = $this->employee;
        $salary = (float) $this->before($e->workLogs(), $date)->sum('amount') - (float) $this->before($e->payouts(), $date)->sum('amount');
        $advance = (float) $this->before($e->advances(), $date)->where('type', AdvanceType::Issue->value)->sum('amount')
            - (float) $this->before($e->advances(), $date)->where('type', AdvanceType::Return->value)->sum('amount')
            - (float) $this->before($e->advanceExpenses(), $date)->sum('amount');

        return [$salary, $advance];
    }

    public function build(): Xlsx
    {
        $e = $this->employee;
        $m = Xlsx::MONEY;
        $x = new Xlsx;

        $work = $this->in($e->workLogs())->with('project')->orderBy('date')->orderBy('id')->get();
        $paid = $this->in($e->payouts())->with('project')->orderBy('date')->orderBy('id')->get();
        $adv = $this->in($e->advances())->orderBy('date')->orderBy('id')->get();
        $spent = $this->in($e->expenses()->where('payment_source', PaymentSource::Advance->value))->with(['project', 'category'])->orderBy('date')->orderBy('id')->get();

        [$salaryStart, $advStart] = $this->balancesAt($this->from);
        [$salaryEnd, $advEnd] = $this->balancesAt($this->until->copy()->addDay());
        $issued = (float) $adv->where('type', AdvanceType::Issue)->sum('amount');
        $returned = (float) $adv->where('type', AdvanceType::Return)->sum('amount');

        $x->sheet('Расчётный листок', ['Показатель' => [40], 'Сумма' => [16]]);
        $x->row(['Сотрудник', $e->name])
            ->row(['Должность', $e->position])
            ->row(['Период', $this->from->format('d.m.Y').' — '.$this->until->format('d.m.Y')])
            ->blank()
            ->title('Зарплата')
            ->row(['Долг по зарплате на начало периода', $salaryStart], [null, $m])
            ->row(['Начислено за период (дней: '.number_format((float) $work->sum('days'), 1, ',', ' ').')', (float) $work->sum('amount')], [null, $m])
            ->row(['Выплачено за период', (float) $paid->sum('amount')], [null, $m])
            ->row([$salaryEnd >= 0 ? 'Долг по зарплате на конец периода' : 'Переплата на конец периода', abs($salaryEnd)], [null, $m], bold: true)
            ->blank()
            ->title('Подотчёт')
            ->row(['На руках на начало периода', $advStart], [null, $m])
            ->row(['Выдано под отчёт', $issued], [null, $m])
            ->row(['Вернул остаток', $returned], [null, $m])
            ->row(['Отчитался чеками', (float) $spent->sum('amount')], [null, $m])
            ->row(['На руках на конец периода', $advEnd], [null, $m], bold: true)
            ->blank()
            ->row(['Сформировано', now()->format('d.m.Y H:i')]);

        $x->sheet('Начислено', ['Дата' => [11], 'Объект' => [30], 'Работы' => [36], 'Дней' => [8], 'Ставка' => [13], 'Начислено' => [14]]);
        foreach ($work as $w) {
            $x->row([$w->date, $w->project?->name, $w->work, $w->days, $w->rate, $w->amount], [null, null, null, Xlsx::NUMBER, $m, $m]);
        }
        $x->row(['Итого', null, null, (float) $work->sum('days'), null, (float) $work->sum('amount')], [null, null, null, Xlsx::NUMBER, null, $m], bold: true);
        $x->blank()->title('По объектам');
        foreach ($work->groupBy(fn ($w) => $w->project?->name ?? '—') as $name => $rows) {
            $x->row([null, $name, null, (float) $rows->sum('days'), null, (float) $rows->sum('amount')], [null, null, null, Xlsx::NUMBER, null, $m]);
        }

        $x->sheet('Выплачено', ['Дата' => [11], 'Сумма' => [14], 'Способ' => [20], 'Объект' => [30], 'Примечание' => [30]]);
        foreach ($paid as $p) {
            $x->row([$p->date, $p->amount, $p->method?->getLabel(), $p->project?->name, $p->notes], [null, $m]);
        }
        $x->row(['Итого', (float) $paid->sum('amount')], [null, $m], bold: true);

        $x->sheet('Подотчёт', ['Дата' => [11], 'Операция' => [22], 'Сумма' => [14], 'Объект / способ' => [30], 'Описание' => [36]]);
        $ops = $adv->map(fn ($a) => [$a->date, $a->type?->getLabel(), (float) $a->amount, $a->method?->getLabel(), $a->notes])
            ->concat($spent->map(fn ($s) => [$s->date, 'Чек: '.$s->category?->name, (float) $s->amount, $s->project?->name, trim($s->supplier.' '.$s->description)]))
            ->sortBy(fn ($r) => $r[0]->format('Y-m-d'))
            ->values();
        foreach ($ops as $r) {
            $x->row($r, [null, null, $m]);
        }

        return $x;
    }
}
