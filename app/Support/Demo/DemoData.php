<?php

namespace App\Support\Demo;

use App\Enums\AdvanceType;
use App\Enums\IncomeType;
use App\Enums\PaymentSource;
use App\Enums\PayMethod;
use App\Enums\ProjectStatus;
use App\Enums\UserRole;
use App\Models\Advance;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Income;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\Payout;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkLog;
use App\Support\CurrentOrganization;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * Данные вымышленной монтажной организации: 4 объекта, 5 сотрудников, расходы, журнал работ, поступления,
 * выплаты, подотчёт, приглашение. Все даты — от сегодняшнего дня («свежие» после каждого пересоздания).
 *
 * Используют: пересоздание публичного демо (DemoReset), локальный DemoSeeder (вход по паролю, скриншоты)
 * и пример в новой организации (ExampleData — отдельные блоки project/expenses/workLogs/incomes с $extra).
 * Записи создаются в текущей организации (CurrentOrganization) — она должна совпадать с $org.
 * Статьи ищутся по названию, переименованные — по признакам (зарплата, налоги) и порядку из справочника по умолчанию.
 */
class DemoData
{
    /** id статей организации: [название => id] */
    private array $byName = [];

    /** [sort => id] активных обычных статей */
    private array $bySort = [];

    private ?int $laborId;

    private ?int $fallbackId;

    private int $projectNo = 0;

    public function __construct(private Organization $org)
    {
        $cats = ExpenseCategory::acrossOrganizations()->where('organization_id', $org->id)->orderBy('sort')->get();
        $this->byName = $cats->pluck('id', 'name')->all();
        $this->bySort = $cats->where('is_labor', false)->where('is_tax', false)->pluck('id', 'sort')->all();
        $this->laborId = ExpenseCategory::laborId($org->id);
        $this->fallbackId = $cats->where('is_labor', false)->where('is_tax', false)->where('is_active', true)->sortByDesc('sort')->first()?->id;
    }

    /**
     * Полный набор демо-данных. $personas — ['admin' => User, 'manager' => User, 'foreman' => User],
     * участники организации: прораб — сотрудник с учётной записью, свои чеки и журнал он «вносит» сам.
     */
    public function fill(array $personas): void
    {
        $this->assertCurrent();

        // Приглашение, которое ещё не приняли, — видно в «Участниках»
        (new Invitation(['email' => 'novyi.prorab@demo.invalid', 'role' => UserRole::Foreman, 'expires_at' => now()->addDays(6)]))
            ->forceFill(['created_by' => $personas['admin']->id])->save();

        $foreman = $this->employee(['name' => $personas['foreman']->name, 'position' => 'Прораб', 'day_rate' => 4500, 'user_id' => $personas['foreman']->id, 'phone' => '+7 900 000-00-01']);
        $workers = [
            $foreman,
            $this->employee(['name' => 'Сергей Кабелев', 'position' => 'Монтажник', 'day_rate' => 3500]),
            $this->employee(['name' => 'Дмитрий Щитов', 'position' => 'Монтажник', 'day_rate' => 3500]),
            $this->employee(['name' => 'Павел Трассов', 'position' => 'Монтажник', 'day_rate' => 3200]),
            $this->employee(['name' => 'Олег Наладкин', 'position' => 'Пусконаладчик', 'day_rate' => 5000]),
        ];

        // 1. Крупный объект в работе: всё идёт по плану.
        $p1 = $this->project('ЖК «Солнечный», корпус 2 — СОУЭ и АПС', 'ООО «Солнечный Девелопмент»', 3_200_000, 6, ProjectStatus::Active, 75, self::BIG_BUDGET);
        $this->expenses($p1, self::BIG_EXPENSES);
        // журнал своей бригады ведёт прораб
        $this->actingAs($personas['foreman'], fn () => $this->workLogs($p1, array_slice($workers, 0, 4), 70, 28));
        $this->incomes($p1, [[72, IncomeType::Advance, 1_280_000], [25, IncomeType::Stage, 800_000]]);

        // 2. Объект с перерасходом материалов — попадает в «Требует внимания».
        $p2 = $this->project('Школа № 12 — видеонаблюдение', 'МОУ «СОШ № 12»', 890_000, 6, ProjectStatus::Active, 40, [
            'Материалы и оборудование' => 380_000, 'Работа (зарплата из журнала)' => 210_000, 'Транспорт и ГСМ' => 25_000, 'Прочее' => 10_000,
        ]);
        $this->expenses($p2, [
            [38, 'Материалы и оборудование', 296_000, 'ООО «ВидеоСистемы»', 'Камеры, регистраторы, HDD'],
            [21, 'Материалы и оборудование', 118_500, 'ООО «ВидеоСистемы»', 'Доп. камеры по просьбе заказчика'],
            [15, 'Транспорт и ГСМ', 9_800, 'АЗС', 'Топливо'],
        ]);
        $this->workLogs($p2, [$workers[1], $workers[4]], 35, 18);
        $this->incomes($p2, [[39, IncomeType::Advance, 445_000]]);

        // 3. Завершён, заказчик не доплатил.
        $p3 = $this->project('Склад «Логистик-Парк» — СКУД', 'ООО «Логистик-Парк»', 1_450_000, 6, ProjectStatus::Completed, 150, [
            'Материалы и оборудование' => 620_000, 'Работа (зарплата из журнала)' => 300_000, 'Субподряд' => 90_000, 'Транспорт и ГСМ' => 40_000,
        ], 60);
        $this->expenses($p3, [
            [145, 'Материалы и оборудование', 402_000, 'ООО «Доступ-Сервис»', 'Контроллеры, считыватели, турникеты'],
            [120, 'Материалы и оборудование', 176_300, 'ООО «КабельОпт»', 'Кабель, короб'],
            [110, 'Субподряд', 90_000, 'ИП Сварщиков', 'Металлоконструкции под турникеты'],
            [100, 'Транспорт и ГСМ', 31_000, 'АЗС', 'Топливо'],
        ]);
        $this->workLogs($p3, [$workers[2], $workers[3], $workers[4]], 140, 75);
        $this->incomes($p3, [[148, IncomeType::Advance, 725_000], [90, IncomeType::Stage, 525_000]]);

        // 4. Планируется: только бюджет.
        $this->project('ТЦ «Меридиан» — пожарная сигнализация', 'АО «Меридиан»', 2_350_000, 6, ProjectStatus::Planned, -14, [
            'Материалы и оборудование' => 900_000, 'Работа (зарплата из журнала)' => 520_000, 'Субподряд' => 150_000, 'Транспорт и ГСМ' => 60_000, 'Прочее' => 30_000,
        ]);

        // Выплаты зарплаты.
        foreach ($workers as $i => $w) {
            foreach ([60, 30] as $ago) {
                Payout::create(['date' => self::ago($ago), 'employee_id' => $w->id, 'amount' => 25_000 + $i * 5_000, 'method' => PayMethod::Card]);
            }
        }

        // Подотчёт прораба: выдано 30 000, отчитался чеками на 17 350 — остаток висит больше 14 дней.
        Advance::create(['date' => self::ago(20), 'employee_id' => $foreman->id, 'type' => AdvanceType::Issue, 'amount' => 30_000, 'method' => PayMethod::Cash]);
        // чеки прораб вносит сам — они видны у него на главной в «Мои расходы за месяц»
        $this->actingAs($personas['foreman'], fn () => $this->expenses($p1, [
            [18, 'Инструмент и расходники', 9_850, 'Строймаркет', 'Дюбели, анкеры, изолента', $foreman],
            [16, 'Прочее', 7_500, 'Столовая «Обед»', 'Питание бригады', $foreman],
        ]));
        // этот чек прораб прислал через бота MAX
        Expense::where('created_by', $personas['foreman']->id)->where('supplier', 'Строймаркет')->update(['source' => 'max']);
    }

    /** Бюджет и расходы крупного объекта — их же берёт пример в новой организации */
    public const BIG_BUDGET = [
        'Материалы и оборудование' => 1_150_000, 'Работа (зарплата из журнала)' => 700_000, 'Субподряд' => 250_000,
        'Транспорт и ГСМ' => 80_000, 'Аренда техники и инструмента' => 40_000, 'Инструмент и расходники' => 35_000, 'Прочее' => 20_000,
    ];

    /** [дней назад, статья, сумма, поставщик, описание] */
    public const BIG_EXPENSES = [
        [70, 'Материалы и оборудование', 480_000, 'ООО «ПожТехКомплект»', 'Приборы АПС, извещатели'],
        [55, 'Материалы и оборудование', 310_000, 'ООО «КабельОпт»', 'Кабель КПСнг(А)-FRLS, 12 км'],
        [30, 'Материалы и оборудование', 145_000, 'ООО «ПожТехКомплект»', 'Речевые оповещатели'],
        [60, 'Субподряд', 120_000, 'ИП Бурилов', 'Штробление и проходы'],
        [50, 'Транспорт и ГСМ', 18_500, 'АЗС', 'Топливо, май'],
        [20, 'Транспорт и ГСМ', 21_300, 'АЗС', 'Топливо, июнь'],
        [45, 'Аренда техники и инструмента', 24_000, 'ООО «Вышки64»', 'Аренда вышки-туры, 2 недели'],
        [40, 'Инструмент и расходники', 12_700, 'Строймаркет', 'Крепёж, гофра, стяжки'],
    ];

    public static function ago(int $days): Carbon
    {
        return now()->startOfDay()->subDays($days);
    }

    /** Сотрудник; $extra — поля не из $fillable (например, is_example) */
    public function employee(array $attributes, array $extra = []): Employee
    {
        $this->assertCurrent();
        $e = new Employee($attributes);
        $e->forceFill($extra)->save();

        return $e;
    }

    /** Объект с бюджетом по статьям [название статьи => сумма] */
    public function project(string $name, string $customer, int $amount, float $tax, ProjectStatus $status, int $startedAgo, array $budget, ?int $endedAgo = null, array $extra = []): Project
    {
        $this->assertCurrent();
        $n = ++$this->projectNo;
        $p = new Project([
            'name' => $name,
            'customer' => $customer,
            'address' => 'г. Энск, ул. Примерная, '.($n * 7),
            'contract_number' => sprintf('%02d/%s', $n, now()->format('Y')),
            'contract_date' => self::ago($startedAgo + 10),
            'contract_amount' => $amount,
            'tax_percent' => $tax,
            'status' => $status,
            'start_date' => self::ago($startedAgo),
            'end_date' => $endedAgo !== null ? self::ago($endedAgo) : null,
        ]);
        $p->forceFill($extra)->save();

        // переименованные статьи могут свестись к одной — суммируем
        $sums = [];
        foreach ($budget as $cat => $sum) {
            $id = $this->category($cat);
            $sums[$id] = ($sums[$id] ?? 0) + $sum;
        }
        foreach ($sums as $id => $sum) {
            $p->budgets()->create(['expense_category_id' => $id, 'amount' => $sum]);
        }

        return $p;
    }

    /** Расходы: [дней назад, статья, сумма, поставщик, описание, ?сотрудник-подотчётник] */
    public function expenses(Project $p, array $rows, array $extra = []): void
    {
        $this->assertCurrent();
        foreach ($rows as $r) {
            [$ago, $cat, $amount, $supplier, $description] = $r;
            $employee = $r[5] ?? null;
            (new Expense([
                'date' => self::ago($ago),
                'project_id' => $p->id,
                'expense_category_id' => $this->category($cat),
                'amount' => $amount,
                'supplier' => $supplier,
                'document' => 'УПД-'.(1000 + ($p->id % 100) * 37 + $ago),
                'payment_source' => $employee ? PaymentSource::Advance : PaymentSource::Bank,
                'employee_id' => $employee?->id,
                'description' => $description,
            ]))->forceFill($extra)->save();
        }
    }

    /** Журнал работ: будни с $fromAgo по $toAgo дней назад, каждый второй-третий день */
    public function workLogs(Project $p, array $team, int $fromAgo, int $toAgo, array $extra = []): void
    {
        $this->assertCurrent();
        $jobs = ['Прокладка кабеля', 'Монтаж оборудования', 'Монтаж кабель-канала', 'Подключение и наладка'];
        foreach ($team as $i => $e) {
            for ($d = $fromAgo; $d >= $toAgo; $d -= 2 + ($i % 2)) {
                $date = self::ago($d);
                if ($date->isWeekend()) {
                    continue;
                }
                (new WorkLog([
                    'date' => $date,
                    'project_id' => $p->id,
                    'employee_id' => $e->id,
                    'work' => $jobs[($d + $i) % count($jobs)],
                    'days' => 1,
                    'rate' => $e->day_rate,
                ]))->forceFill($extra)->save();
            }
        }
    }

    /** Поступления от заказчика: [дней назад, тип, сумма] */
    public function incomes(Project $p, array $rows, array $extra = []): void
    {
        $this->assertCurrent();
        foreach ($rows as [$ago, $type, $amount]) {
            (new Income([
                'date' => self::ago($ago), 'project_id' => $p->id, 'amount' => $amount,
                'type' => $type, 'method' => PayMethod::Bank, 'document' => 'п/п '.(200 + $ago),
            ]))->forceFill($extra)->save();
        }
    }

    /** id статьи по названию; переименованной — по признаку зарплаты или по порядку справочника по умолчанию */
    public function category(string $name): int
    {
        if (isset($this->byName[$name])) {
            return $this->byName[$name];
        }

        foreach (Organization::DEFAULT_CATEGORIES as [$default, $labor, $tax, $sort]) {
            if ($default !== $name) {
                continue;
            }
            if ($labor && $this->laborId) {
                return $this->laborId;
            }
            if (! $labor && ! $tax && isset($this->bySort[$sort])) {
                return $this->bySort[$sort];
            }
        }

        return $this->fallbackId ?? throw new LogicException("В организации {$this->org->id} нет статьи для «{$name}»");
    }

    /** Записи, созданные внутри $fn, получают created_by этого пользователя (TracksCreator) */
    private function actingAs(User $user, callable $fn): void
    {
        $previous = auth()->user();
        auth()->setUser($user);
        try {
            $fn();
        } finally {
            $previous ? auth()->setUser($previous) : auth()->forgetUser();
        }
    }

    private function assertCurrent(): void
    {
        if ((int) app(CurrentOrganization::class)->id() !== (int) $this->org->id) {
            throw new LogicException('DemoData: текущая организация не та, которую заполняем');
        }
    }
}
