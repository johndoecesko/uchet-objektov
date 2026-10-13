<?php

namespace App\Reports;

use App\Models\Project;
use App\Support\Xlsx;

/** Выгрузка по объекту: сводка, план/факт по статьям, расходы, работы, поступления за весь срок */
class ProjectReport
{
    public function __construct(private Project $project) {}

    public function filename(): string
    {
        return 'objekt-'.$this->project->id.'-'.now()->format('Y-m-d').'.xlsx';
    }

    public function build(): Xlsx
    {
        $p = $this->project;
        $m = Xlsx::MONEY;
        $x = new Xlsx;

        $expenses = $p->expensesTotal();
        $labor = $p->laborTotal();
        $tax = $p->taxTotal();

        $x->sheet('Сводка', ['Показатель' => [34], 'Значение' => [50]]);
        foreach ([
            ['Объект', $p->name],
            ['Заказчик', $p->customer],
            ['ИНН / КПП', trim($p->customer_inn.' / '.$p->customer_kpp, ' /') ?: null],
            ['Адрес', $p->address],
            ['Договор №', $p->contract_number],
            ['Дата договора', $p->contract_date],
            ['Статус', $p->status?->getLabel()],
            ['Начало', $p->start_date],
            ['Окончание', $p->end_date],
        ] as $row) {
            $x->row($row);
        }
        $x->blank();
        foreach ([
            ['Сумма договора', (float) $p->contract_amount],
            ['Получено от заказчика', $p->receivedTotal()],
            ['Долг заказчика', $p->customerDebt()],
            ['Затраты: расходы (материалы, ГСМ и пр.)', $expenses],
            ['Затраты: зарплата по журналу работ', $labor],
            ['Затраты: налоги ('.rtrim(rtrim(number_format((float) $p->tax_percent, 2, ',', ''), '0'), ',').'% от договора)', $tax],
        ] as $row) {
            $x->row($row, [null, $m]);
        }
        $x->row(['Затраты всего', $expenses + $labor + $tax], [null, $m], bold: true);
        $x->row(['Маржа по договору (договор − затраты)', $p->contractMargin()], [null, $m]);
        $x->row(['Денежный результат (получено − затраты)', $p->cashResult()], [null, $m]);
        $x->row(['Ожидаемые затраты (по статьям большее из плана и факта)', $p->forecastCost()], [null, $m]);
        $x->row(['Прогноз прибыли', $p->forecastProfit()], [null, $m], bold: true);
        $x->blank()->row(['Сформировано', now()->format('d.m.Y H:i')]);

        $x->sheet('План-факт по статьям', ['Статья' => [36], 'План' => [16], 'Факт' => [16], 'Остаток' => [16], 'Освоение' => [11]]);
        $rows = $p->planFact();
        foreach ($rows as $r) {
            $x->row([$r['name'], $r['plan'], $r['fact'], $r['rest'], $r['plan'] > 0 ? $r['fact'] / $r['plan'] : null], [null, $m, $m, $m, Xlsx::PERCENT]);
        }
        $plan = $rows->sum('plan');
        $fact = $rows->sum('fact');
        $x->row(['Итого', $plan, $fact, $plan - $fact, $plan > 0 ? $fact / $plan : null], [null, $m, $m, $m, Xlsx::PERCENT], bold: true);

        $x->sheet('Расходы', ['Дата' => [11], 'Статья' => [24], 'Сумма' => [14], 'Поставщик' => [24], 'Документ' => [16],
            'Чем оплачено' => [20], 'Подотчёт' => [20], 'Что купили' => [40], 'Чеков' => [7], 'Внёс' => [18]]);
        $list = $p->expenses()->with(['category', 'employee', 'creator'])->orderBy('date')->orderBy('id')->get();
        foreach ($list as $e) {
            $x->row([$e->date, $e->category?->name, $e->amount, $e->supplier, $e->document, $e->payment_source?->getLabel(),
                $e->employee?->name, $e->description, count($e->attachments ?? []) ?: null, $e->creator?->name], [null, null, $m]);
        }
        $x->row(['Итого', null, (float) $list->sum('amount')], [null, null, $m], bold: true);

        $x->sheet('Работы', ['Дата' => [11], 'Сотрудник' => [24], 'Работы' => [40], 'Дней' => [8], 'Ставка' => [13], 'Начислено' => [14], 'Примечание' => [30]]);
        $list = $p->workLogs()->with('employee')->orderBy('date')->orderBy('id')->get();
        foreach ($list as $w) {
            $x->row([$w->date, $w->employee?->name, $w->work, $w->days, $w->rate, $w->amount, $w->notes], [null, null, null, Xlsx::NUMBER, $m, $m]);
        }
        $x->row(['Итого', null, null, (float) $list->sum('days'), null, (float) $list->sum('amount')], [null, null, null, Xlsx::NUMBER, null, $m], bold: true);

        $x->sheet('Поступления', ['Дата' => [11], 'Тип' => [22], 'Сумма' => [14], 'Способ' => [20], 'Документ' => [20], 'Примечание' => [30]]);
        $list = $p->incomes()->orderBy('date')->orderBy('id')->get();
        foreach ($list as $i) {
            $x->row([$i->date, $i->type?->getLabel(), $i->amount, $i->method?->getLabel(), $i->document, $i->notes], [null, null, $m]);
        }
        $x->row(['Итого', null, (float) $list->sum('amount')], [null, null, $m], bold: true);

        return $x;
    }
}
