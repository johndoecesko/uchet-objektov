<?php

namespace App\Support\Demo;

use App\Enums\IncomeType;
use App\Enums\ProjectStatus;
use App\Models\Activity;
use App\Models\Advance;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Organization;
use App\Models\Payout;
use App\Models\Project;
use App\Models\ProjectBudget;
use App\Models\WorkLog;
use App\Support\CurrentOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * «Заполнить примером» / «Удалить пример» в новой организации (чек-лист «Первые шаги»).
 * Пример: объект «Пример — …» с бюджетом, расходами, журналом работ двух сотрудников «Пример — …» и поступлениями.
 * Помечены только корневые записи (projects.is_example, employees.is_example) — по ним удаление находит остальное.
 * История при заполнении не пишется; удаление — сразу и окончательно (вместе с корзиной, историей и файлами).
 */
class ExampleData
{
    public const PROJECT_NAME = 'Пример — ЖК «Солнечный», корпус 2';

    public static function exists(): bool
    {
        return Project::query()->example()->exists() || Employee::query()->example()->exists();
    }

    /** Добавить пример в организацию (она же текущая). Пример уже есть — исключение */
    public static function add(Organization $org): Project
    {
        return self::inOrganization($org, function () use ($org) {
            if (self::exists()) {
                throw new LogicException('Пример уже добавлен');
            }

            return activity()->withoutLogs(fn () => DB::transaction(function () use ($org) {
                $data = new DemoData($org);
                $workers = [
                    $data->employee(['name' => 'Пример — Сергей Кабелев', 'position' => 'Монтажник', 'day_rate' => 3500], ['is_example' => true]),
                    $data->employee(['name' => 'Пример — Дмитрий Щитов', 'position' => 'Монтажник', 'day_rate' => 3500], ['is_example' => true]),
                ];
                $project = $data->project(self::PROJECT_NAME, 'ООО «Солнечный Девелопмент» (пример)', 3_200_000, 6, ProjectStatus::Active, 45,
                    DemoData::BIG_BUDGET, extra: ['is_example' => true, 'notes' => 'Пример, чтобы посмотреть, как всё работает. Удалить — «Удалить пример» на главной.']);
                // те же расходы, что в демо, только недавние
                $data->expenses($project, array_map(fn ($r) => [max(1, $r[0] - 30), ...array_slice($r, 1)], array_slice(DemoData::BIG_EXPENSES, 0, 6)));
                $data->workLogs($project, $workers, 40, 5);
                $data->incomes($project, [[44, IncomeType::Advance, 1_280_000], [10, IncomeType::Stage, 600_000]]);

                return $project;
            }));
        });
    }

    /**
     * Удалить пример целиком: объекты-примеры со всем, что в них внесено (в том числе пользователем), и сотрудников-примеров.
     * Сотрудника, который уже работает на настоящих объектах (или ему внесены выплаты и подотчёт), не удаляем —
     * снимаем пометку «Пример».
     *
     * @return array{records: int, kept: list<string>}
     */
    public static function remove(Organization $org): array
    {
        return self::inOrganization($org, fn () => activity()->withoutLogs(fn () => DB::transaction(function () use ($org) {
            $projectIds = Project::acrossOrganizations()->where('organization_id', $org->id)->where('is_example', true)->pluck('id')->all();
            $employeeIds = Employee::acrossOrganizations()->where('organization_id', $org->id)->where('is_example', true)->pluck('id')->all();
            $removed = [];
            $kept = [];

            // всё внесённое в объекты-примеры — по одной записи, чтобы удалились и приложенные файлы
            foreach ([Expense::class, WorkLog::class, Income::class, Payout::class] as $class) {
                self::forceDeleteEach($class::withTrashed()->where('organization_id', $org->id)->whereIn('project_id', $projectIds), $removed);
            }
            self::forceDeleteEach(ProjectBudget::query()->where('organization_id', $org->id)->whereIn('project_id', $projectIds), $removed);

            foreach (Employee::query()->whereIn('id', $employeeIds)->get() as $employee) {
                $used = Expense::withTrashed()->where('employee_id', $employee->id)->exists()
                    || WorkLog::withTrashed()->where('employee_id', $employee->id)->exists()
                    || Payout::withTrashed()->where('employee_id', $employee->id)->exists()
                    || Advance::withTrashed()->where('employee_id', $employee->id)->exists();
                if ($used) {
                    $employee->forceFill(['is_example' => false])->save();
                    $kept[] = $employee->name;

                    continue;
                }
                $removed[] = [Employee::class, $employee->id];
                $employee->delete();
            }

            foreach (Project::query()->whereIn('id', $projectIds)->get() as $project) {
                $removed[] = [Project::class, $project->id];
                $project->delete();
            }

            // история того, что пользователь успел внести в пример (сам пример историю не писал)
            foreach (collect($removed)->groupBy(0) as $class => $rows) {
                Activity::acrossOrganizations()->where('organization_id', $org->id)
                    ->where('subject_type', (new $class)->getMorphClass())
                    ->whereIn('subject_id', $rows->pluck(1))
                    ->delete();
            }

            return ['records' => count($removed), 'kept' => $kept];
        })));
    }

    /** Что удалится — для окна подтверждения */
    public static function summary(): array
    {
        $projectIds = Project::query()->example()->pluck('id');

        return [
            'projects' => $projectIds->count(),
            'employees' => Employee::query()->example()->count(),
            'expenses' => Expense::withTrashed()->whereIn('project_id', $projectIds)->count(),
            'work_logs' => WorkLog::withTrashed()->whereIn('project_id', $projectIds)->count(),
            'incomes' => Income::withTrashed()->whereIn('project_id', $projectIds)->count(),
        ];
    }

    private static function forceDeleteEach($query, array &$removed): void
    {
        foreach ($query->get() as $record) {
            /** @var Model $record */
            $removed[] = [$record::class, $record->getKey()];
            method_exists($record, 'forceDelete') ? $record->forceDelete() : $record->delete();
        }
    }

    private static function inOrganization(Organization $org, \Closure $callback): mixed
    {
        $current = app(CurrentOrganization::class)->id();
        if ($current !== null && (int) $current !== (int) $org->id) {
            throw new LogicException('Пример — только в текущей организации');
        }

        return app(CurrentOrganization::class)->run($org, $callback);
    }
}
