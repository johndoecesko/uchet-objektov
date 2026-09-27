<?php

namespace App\Support;

use App\Enums\AdvanceType;
use App\Enums\ProjectStatus;
use App\Filament\Resources\EmployeeResource;
use App\Filament\Resources\ProjectResource;
use App\Models\Advance;
use App\Models\Employee;
use App\Models\Project;
use Illuminate\Support\Collection;

/** Что требует внимания: перерасходы, низкая маржа, незакрытый подотчёт, долги */
class Attention
{
    public const ADVANCE_DAYS = 14;

    public const LOW_MARGIN = 10.0;

    /** @return Collection<int, array{level:string, title:string, text:string, amount:float, url:string}> */
    public static function items(): Collection
    {
        $items = collect();

        $projects = Project::query()
            ->whereIn('status', [ProjectStatus::Active->value, ProjectStatus::Completed->value])
            ->get();

        foreach ($projects as $p) {
            $url = ProjectResource::getUrl('view', ['record' => $p]);
            $cost = $p->costTotal();
            $contract = (float) $p->contract_amount;

            if ($p->status === ProjectStatus::Active) {
                foreach ($p->planFact() as $r) {
                    if ($r['plan'] > 0 && $r['fact'] > $r['plan']) {
                        $items->push(['level' => 'danger', 'title' => $p->name,
                            'text' => "Перерасход по статье «{$r['name']}»: ".Money::fmt($r['fact'] - $r['plan']),
                            'amount' => $r['fact'] - $r['plan'], 'url' => $url]);
                    }
                }
                if ($contract > 0 && $cost > $contract) {
                    $items->push(['level' => 'danger', 'title' => $p->name,
                        'text' => 'Затраты превысили сумму договора на '.Money::fmt($cost - $contract),
                        'amount' => $cost - $contract, 'url' => $url]);
                } elseif ($contract > 0) {
                    $forecast = $p->forecastProfit();
                    if ($forecast < 0) {
                        $items->push(['level' => 'danger', 'title' => $p->name,
                            'text' => 'По прогнозу объект в убытке: '.Money::fmt($forecast),
                            'amount' => -$forecast, 'url' => $url]);
                    } elseif ($forecast / $contract * 100 < self::LOW_MARGIN) {
                        $items->push(['level' => 'warning', 'title' => $p->name,
                            'text' => 'Прогноз рентабельности '.Money::pct($forecast, $contract).' (ниже '.(int) self::LOW_MARGIN.'%)',
                            'amount' => $forecast, 'url' => $url]);
                    }
                }
            }

            if ($p->status === ProjectStatus::Completed && $contract > 0) {
                $debt = $p->customerDebt();
                if ($debt > 0.009) {
                    $items->push(['level' => 'warning', 'title' => $p->name,
                        'text' => 'Объект завершён, заказчик должен '.Money::fmt($debt).($p->customer ? " ({$p->customer})" : ''),
                        'amount' => $debt, 'url' => $url]);
                }
            }
        }

        foreach (Employee::query()->where('is_active', true)->get() as $e) {
            $bal = $e->advanceBalance();
            if ($bal > 0.009) {
                $last = Advance::where('employee_id', $e->id)->where('type', AdvanceType::Issue->value)->max('date');
                $days = $last ? (int) now()->startOfDay()->diffInDays(\Illuminate\Support\Carbon::parse($last), true) : 0;
                if ($days >= self::ADVANCE_DAYS) {
                    $items->push(['level' => 'danger', 'title' => $e->name,
                        'text' => 'Не отчитался за '.Money::fmt($bal).", последняя выдача {$days} дн. назад",
                        'amount' => $bal, 'url' => EmployeeResource::getUrl('view', ['record' => $e])]);
                }
            }
        }

        return $items
            ->sortBy([fn ($a, $b) => ($a['level'] === 'danger' ? 0 : 1) <=> ($b['level'] === 'danger' ? 0 : 1), fn ($a, $b) => $b['amount'] <=> $a['amount']])
            ->values();
    }
}
