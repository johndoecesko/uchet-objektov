<?php

namespace Database\Seeders;

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
use App\Models\Payout;
use App\Models\Project;
use App\Models\User;
use App\Models\WorkLog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Демо-данные: вымышленная монтажная организация, 4 объекта, 5 сотрудников.
 *
 *   php artisan db:seed --class=DemoSeeder
 *
 * Входы (пароль у всех — demo12345):
 *   admin@demo.local    — администратор
 *   manager@demo.local  — менеджер / бухгалтер
 *   foreman@demo.local  — прораб / монтажник
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'demo12345';

    private array $cat = [];

    public function run(): void
    {
        if (app()->isProduction() && Project::query()->exists()) {
            $this->command?->error('В базе уже есть объекты — демо-данные не добавлены, чтобы не смешать их с рабочими.');

            return;
        }

        $this->call(DatabaseSeeder::class);
        $this->cat = ExpenseCategory::query()->pluck('id', 'name')->all();

        $u = [];
        $users = [
            ['admin@demo.local', 'Анна Директорова', UserRole::Admin],
            ['manager@demo.local', 'Марина Счётова', UserRole::Manager],
            ['foreman@demo.local', 'Игорь Монтажов', UserRole::Foreman],
        ];
        foreach ($users as [$email, $name, $role]) {
            $u[$role->value] = User::updateOrCreate(['email' => $email], [
                'name' => $name, 'password' => self::PASSWORD, 'role' => $role, 'is_active' => true,
            ]);
        }

        $foreman = Employee::create(['name' => 'Игорь Монтажов', 'position' => 'Прораб', 'day_rate' => 4500, 'user_id' => $u['foreman']->id, 'phone' => '+7 900 000-00-01']);
        $workers = [
            $foreman,
            Employee::create(['name' => 'Сергей Кабелев', 'position' => 'Монтажник', 'day_rate' => 3500]),
            Employee::create(['name' => 'Дмитрий Щитов', 'position' => 'Монтажник', 'day_rate' => 3500]),
            Employee::create(['name' => 'Павел Трассов', 'position' => 'Монтажник', 'day_rate' => 3200]),
            Employee::create(['name' => 'Олег Наладкин', 'position' => 'Пусконаладчик', 'day_rate' => 5000]),
        ];

        // 1. Крупный объект в работе: всё идёт по плану.
        $p1 = $this->project('ЖК «Солнечный», корпус 2 — СОУЭ и АПС', 'ООО «Солнечный Девелопмент»', 3_200_000, 6, ProjectStatus::Active, 75, [
            'Материалы и оборудование' => 1_150_000, 'Работа (зарплата из журнала)' => 700_000, 'Субподряд' => 250_000,
            'Транспорт и ГСМ' => 80_000, 'Аренда техники и инструмента' => 40_000, 'Инструмент и расходники' => 35_000, 'Прочее' => 20_000,
        ]);
        $this->expenses($p1, [
            [70, 'Материалы и оборудование', 480_000, 'ООО «ПожТехКомплект»', 'Приборы АПС, извещатели'],
            [55, 'Материалы и оборудование', 310_000, 'ООО «КабельОпт»', 'Кабель КПСнг(А)-FRLS, 12 км'],
            [30, 'Материалы и оборудование', 145_000, 'ООО «ПожТехКомплект»', 'Речевые оповещатели'],
            [60, 'Субподряд', 120_000, 'ИП Бурилов', 'Штробление и проходы'],
            [50, 'Транспорт и ГСМ', 18_500, 'АЗС', 'Топливо, май'],
            [20, 'Транспорт и ГСМ', 21_300, 'АЗС', 'Топливо, июнь'],
            [45, 'Аренда техники и инструмента', 24_000, 'ООО «Вышки64»', 'Аренда вышки-туры, 2 недели'],
            [40, 'Инструмент и расходники', 12_700, 'Строймаркет', 'Крепёж, гофра, стяжки'],
        ]);
        // журнал своей бригады ведёт прораб
        $this->actingAs($u['foreman'], fn () => $this->workLogs($p1, array_slice($workers, 0, 4), 70, 28));
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
                Payout::create(['date' => $this->ago($ago), 'employee_id' => $w->id, 'amount' => 25_000 + $i * 5_000, 'method' => PayMethod::Card]);
            }
        }

        // Подотчёт прораба: выдано 30 000, отчитался чеками на 17 350 — остаток висит больше 14 дней.
        Advance::create(['date' => $this->ago(20), 'employee_id' => $foreman->id, 'type' => AdvanceType::Issue, 'amount' => 30_000, 'method' => PayMethod::Cash]);
        // чеки прораб вносит сам — они видны у него на главной в «Мои расходы за месяц»
        $this->actingAs($u['foreman'], fn () => $this->expenses($p1, [
            [18, 'Инструмент и расходники', 9_850, 'Строймаркет', 'Дюбели, анкеры, изолента', $foreman],
            [16, 'Прочее', 7_500, 'Столовая «Обед»', 'Питание бригады', $foreman],
        ]));
    }

    /** Записи, созданные внутри $fn, получают created_by этого пользователя (через TracksCreator). */
    private function actingAs(User $user, callable $fn): void
    {
        auth()->setUser($user);
        try {
            $fn();
        } finally {
            auth()->forgetUser();
        }
    }

    private function ago(int $days): Carbon
    {
        return now()->startOfDay()->subDays($days);
    }

    private function project(string $name, string $customer, int $amount, float $tax, ProjectStatus $status, int $startedAgo, array $budget, ?int $endedAgo = null): Project
    {
        static $n = 0;
        $n++;
        $p = Project::create([
            'name' => $name,
            'customer' => $customer,
            'address' => 'г. Энск, ул. Примерная, '.($n * 7),
            'contract_number' => sprintf('%02d/%s', $n, now()->format('Y')),
            'contract_date' => $this->ago($startedAgo + 10),
            'contract_amount' => $amount,
            'tax_percent' => $tax,
            'status' => $status,
            'start_date' => $this->ago($startedAgo),
            'end_date' => $endedAgo !== null ? $this->ago($endedAgo) : null,
        ]);
        foreach ($budget as $cat => $sum) {
            $p->budgets()->create(['expense_category_id' => $this->cat[$cat], 'amount' => $sum]);
        }

        return $p;
    }

    private function expenses(Project $p, array $rows): void
    {
        foreach ($rows as $r) {
            [$ago, $cat, $amount, $supplier, $description] = $r;
            $employee = $r[5] ?? null;
            Expense::create([
                'date' => $this->ago($ago),
                'project_id' => $p->id,
                'expense_category_id' => $this->cat[$cat],
                'amount' => $amount,
                'supplier' => $supplier,
                'document' => 'УПД-'.(1000 + $p->id * 37 + $ago),
                'payment_source' => $employee ? PaymentSource::Advance : PaymentSource::Bank,
                'employee_id' => $employee?->id,
                'description' => $description,
            ]);
        }
    }

    /** Журнал работ: будни с $fromAgo по $toAgo дней назад, каждый второй-третий день. */
    private function workLogs(Project $p, array $team, int $fromAgo, int $toAgo): void
    {
        $jobs = ['Прокладка кабеля', 'Монтаж оборудования', 'Монтаж кабель-канала', 'Подключение и наладка'];
        foreach ($team as $i => $e) {
            for ($d = $fromAgo; $d >= $toAgo; $d -= 2 + ($i % 2)) {
                $date = $this->ago($d);
                if ($date->isWeekend()) {
                    continue;
                }
                WorkLog::create([
                    'date' => $date,
                    'project_id' => $p->id,
                    'employee_id' => $e->id,
                    'work' => $jobs[($d + $i) % count($jobs)],
                    'days' => 1,
                    'rate' => $e->day_rate,
                ]);
            }
        }
    }

    private function incomes(Project $p, array $rows): void
    {
        foreach ($rows as [$ago, $type, $amount]) {
            Income::create([
                'date' => $this->ago($ago), 'project_id' => $p->id, 'amount' => $amount,
                'type' => $type, 'method' => PayMethod::Bank, 'document' => 'п/п '.(200 + $ago),
            ]);
        }
    }
}
