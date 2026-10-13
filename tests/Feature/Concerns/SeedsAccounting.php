<?php

namespace Tests\Feature\Concerns;

use App\Enums\AdvanceType;
use App\Enums\PaymentSource;
use App\Enums\UserRole;
use App\Models\Advance;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Income;
use App\Models\Payout;
use App\Models\Project;
use App\Models\ProjectBudget;
use App\Models\User;
use App\Models\WorkLog;
use Database\Seeders\DatabaseSeeder;

/** Общие данные для тестов учёта: справочники, админ, прораб, объект, сотрудник и набор операций */
trait SeedsAccounting
{
    protected User $admin;

    protected User $foreman;

    protected Project $project;

    protected Employee $emp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->admin = User::create(['name' => 'Админ', 'email' => 'a@test.ru', 'password' => 'secret123', 'role' => UserRole::Admin]);
        $this->foreman = User::create(['name' => 'Прораб', 'email' => 'f@test.ru', 'password' => 'secret123', 'role' => UserRole::Foreman]);

        $this->project = Project::create(['name' => 'Школа №5, АПС', 'customer' => 'МОУ СОШ №5', 'contract_amount' => 1000000, 'status' => 'active']);
        $this->emp = Employee::create(['name' => 'Иванов И.И.', 'day_rate' => 3000]);
    }

    protected function cat(string $like): ExpenseCategory
    {
        return ExpenseCategory::where('name', 'like', "%{$like}%")->firstOrFail();
    }

    protected function fillData(): void
    {
        $mat = $this->cat('Материалы');
        ProjectBudget::create(['project_id' => $this->project->id, 'expense_category_id' => $mat->id, 'amount' => 300000]);
        ProjectBudget::create(['project_id' => $this->project->id, 'expense_category_id' => ExpenseCategory::laborId(), 'amount' => 200000]);

        Expense::create(['date' => '2026-09-01', 'project_id' => $this->project->id, 'expense_category_id' => $mat->id, 'amount' => 120000, 'payment_source' => PaymentSource::Bank]);
        Expense::create(['date' => '2026-09-02', 'project_id' => $this->project->id, 'expense_category_id' => $this->cat('ГСМ')->id, 'amount' => 5000, 'payment_source' => PaymentSource::Advance, 'employee_id' => $this->emp->id]);
        // employee_id должен обнулиться, если оплата не из подотчёта
        Expense::create(['date' => '2026-09-02', 'project_id' => $this->project->id, 'expense_category_id' => $mat->id, 'amount' => 1000, 'payment_source' => PaymentSource::Cash, 'employee_id' => $this->emp->id]);

        WorkLog::create(['date' => '2026-09-01', 'project_id' => $this->project->id, 'employee_id' => $this->emp->id, 'days' => 2, 'rate' => 3000]);
        WorkLog::create(['date' => '2026-09-03', 'project_id' => $this->project->id, 'employee_id' => $this->emp->id, 'days' => 1, 'rate' => 3000, 'amount' => 3500]);

        Payout::create(['date' => '2026-09-05', 'employee_id' => $this->emp->id, 'amount' => 4000, 'method' => 'card']);
        Advance::create(['date' => '2026-09-01', 'employee_id' => $this->emp->id, 'type' => AdvanceType::Issue, 'amount' => 10000, 'method' => 'cash']);
        Advance::create(['date' => '2026-09-04', 'employee_id' => $this->emp->id, 'type' => AdvanceType::Return, 'amount' => 2000, 'method' => 'cash']);

        Income::create(['date' => '2026-09-01', 'project_id' => $this->project->id, 'amount' => 300000, 'type' => 'advance', 'method' => 'bank']);
    }
}
