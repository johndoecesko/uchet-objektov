<?php

namespace Tests\Feature;

use App\Filament\Resources\EmployeeResource\Pages\ViewEmployee;
use App\Filament\Resources\ProjectResource\Pages\ListProjects;
use App\Filament\Resources\ProjectResource\Pages\ViewProject;
use App\Models\Project;
use App\Reports\OwnerReport;
use App\Reports\PayslipReport;
use App\Reports\ProjectReport;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
use Tests\Feature\Concerns\SeedsAccounting;
use Tests\TestCase;

class ExportTest extends TestCase
{
    use RefreshDatabase, SeedsAccounting;

    /** @return array<string, list<list<mixed>>> лист => строки */
    private function read(string $path): array
    {
        $reader = new Reader;
        $reader->open($path);
        $out = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $out[$sheet->getName()][] = $row->toArray();
            }
        }
        $reader->close();
        unlink($path);

        return $out;
    }

    /** Значение второй колонки по подписи в первой */
    private function value(array $rows, string $label): mixed
    {
        foreach ($rows as $r) {
            if (($r[0] ?? null) === $label || (is_string($r[0] ?? null) && str_starts_with($r[0], $label))) {
                return $r[1] ?? null;
            }
        }
        $this->fail("Нет строки «{$label}»");
    }

    public function test_project_report(): void
    {
        $this->fillData();
        $this->project->update(['tax_percent' => 6]);
        $report = new ProjectReport($this->project->fresh());
        $this->assertSame('objekt-'.$this->project->id.'-'.now()->format('Y-m-d').'.xlsx', $report->filename());

        $x = $this->read($report->build()->save());
        $this->assertSame(['Сводка', 'План-факт по статьям', 'Расходы', 'Работы', 'Поступления'], array_keys($x));

        $s = $x['Сводка'];
        $this->assertSame('Школа №5, АПС', $this->value($s, 'Объект'));
        $this->assertEquals(1000000, $this->value($s, 'Сумма договора'));
        $this->assertEquals(300000, $this->value($s, 'Получено от заказчика'));
        $this->assertEquals(126000, $this->value($s, 'Затраты: расходы'));
        $this->assertEquals(9500, $this->value($s, 'Затраты: зарплата'));
        $this->assertEquals(60000, $this->value($s, 'Затраты: налоги (6%'));
        $this->assertEquals(195500, $this->value($s, 'Затраты всего'));
        $this->assertEquals(435000, $this->value($s, 'Прогноз прибыли'));
        $this->assertIsNotString($this->value($s, 'Сумма договора')); // число, а не строка

        $exp = $x['Расходы'];
        $this->assertSame('Дата', $exp[0][0]);
        $this->assertInstanceOf(DateTimeInterface::class, $exp[1][0]);
        $this->assertSame('2026-09-01', $exp[1][0]->format('Y-m-d'));
        $this->assertEquals(120000, $exp[1][2]);
        $this->assertIsNotString($exp[1][2]);
        $this->assertEquals(126000, end($exp)[2]);

        $this->assertEquals(9500, end($x['Работы'])[5]);
        $this->assertEquals(3, end($x['Работы'])[3]);
        $this->assertEquals(300000, end($x['Поступления'])[2]);
        $pf = collect($x['План-факт по статьям'])->keyBy(0);
        $this->assertEquals(121000, $pf['Материалы и оборудование'][2]);
        $this->assertEqualsWithDelta(121000 / 300000, $pf['Материалы и оборудование'][4], 0.0001);
    }

    public function test_owner_report_by_period_and_status(): void
    {
        $this->fillData();
        $other = Project::create(['name' => 'Архивный', 'contract_amount' => 500, 'status' => 'archived']);

        $report = new OwnerReport(Carbon::parse('2026-09-02'), Carbon::parse('2026-09-30'), ['active', 'planned']);
        $this->assertSame('sobstvenniku-2026-09-02-2026-09-30.xlsx', $report->filename());
        $rows = $this->read($report->build()->save())['Объекты'];

        $this->assertCount(3, array_filter($rows, fn ($r) => $r !== [] && ! in_array($r[0], ['Период', 'Статусы', 'Примечание', 'Сформировано'], true)));
        [$head, $p, $total] = $rows;
        $this->assertSame('Объект', $head[0]);
        $this->assertSame('Школа №5, АПС', $p[0]);
        $this->assertEquals(0, $p[4]);          // поступление 01.09 — вне периода
        $this->assertEquals(6000, $p[5]);       // 5000 + 1000 от 02.09
        $this->assertEquals(3500, $p[6]);       // работа 03.09
        $this->assertEquals(135500, $p[8]);     // затраты за весь срок
        $this->assertEquals(864500, $p[9]);
        $this->assertSame('Итого', $total[0]);
        $this->assertEquals(1000000, $total[3]);
        $this->assertNotContains('Архивный', array_column($rows, 0));
        $this->assertNotNull($other);
    }

    public function test_payslip(): void
    {
        $this->fillData();
        $report = new PayslipReport($this->emp, Carbon::parse('2026-09-02'), Carbon::parse('2026-09-30'));
        $this->assertSame('listok-'.$this->emp->id.'-2026-09-02-2026-09-30.xlsx', $report->filename());
        $x = $this->read($report->build()->save());
        $s = $x['Расчётный листок'];

        $this->assertEquals(6000, $this->value($s, 'Долг по зарплате на начало'));   // 01.09: 2 дня × 3000
        $this->assertEquals(3500, $this->value($s, 'Начислено за период'));
        $this->assertEquals(4000, $this->value($s, 'Выплачено за период'));
        $this->assertEquals(5500, $this->value($s, 'Долг по зарплате на конец'));
        $this->assertEquals(10000, $this->value($s, 'На руках на начало'));          // выдано 01.09
        $this->assertEquals(2000, $this->value($s, 'Вернул остаток'));
        $this->assertEquals(5000, $this->value($s, 'Отчитался чеками'));
        $this->assertEquals(3000, $this->value($s, 'На руках на конец'));
        $this->assertSame(['Дата', 'Объект', 'Работы', 'Дней', 'Ставка', 'Начислено'], $x['Начислено'][0]);
        $this->assertEquals(3500, $x['Начислено'][1][5]);   // одна запись в периоде (03.09)
        $this->assertSame('Итого', $x['Начислено'][2][0]);
        $this->assertEquals(4000, end($x['Выплачено'])[1]);
    }

    public function test_download_buttons_for_office(): void
    {
        $this->fillData();
        $this->actingAs($this->admin);

        Livewire::test(ViewProject::class, ['record' => $this->project->getKey()])
            ->callAction('exportXlsx')
            ->assertFileDownloaded('objekt-'.$this->project->id.'-'.now()->format('Y-m-d').'.xlsx');

        Livewire::test(ListProjects::class)
            ->callAction('ownerReport', data: ['from' => '2026-09-01', 'until' => '2026-09-30', 'statuses' => ['active']])
            ->assertHasNoActionErrors()
            ->assertFileDownloaded('sobstvenniku-2026-09-01-2026-09-30.xlsx');

        Livewire::test(ViewEmployee::class, ['record' => $this->emp->getKey()])
            ->callAction('payslip', data: ['from' => '2026-09-01', 'until' => '2026-09-30'])
            ->assertFileDownloaded('listok-'.$this->emp->id.'-2026-09-01-2026-09-30.xlsx');
    }

    public function test_foreman_has_no_exports(): void
    {
        $this->actingAs($this->foreman);
        Livewire::test(ViewProject::class, ['record' => $this->project->getKey()])->assertActionHidden('exportXlsx');
        Livewire::test(ListProjects::class)->assertActionHidden('ownerReport');
    }
}
