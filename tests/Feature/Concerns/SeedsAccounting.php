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
use App\Models\Organization;
use App\Models\Payout;
use App\Models\Project;
use App\Models\ProjectBudget;
use App\Models\User;
use App\Models\WorkLog;
use Filament\Facades\Filament;

/**
 * Общие данные для тестов учёта: организация (она же текущая), админ, прораб, объект, сотрудник и набор операций.
 * Справочник статей создаётся вместе с организацией.
 */
trait SeedsAccounting
{
    protected Organization $org;

    protected User $admin;

    protected User $foreman;

    protected Project $project;

    protected Employee $emp;

    protected function setUp(): void
    {
        parent::setUp();

        $this->org = Organization::create(['name' => 'ООО «Монтаж»', 'slug' => 'montazh']);
        $this->useOrganization($this->org);

        $this->admin = $this->member('Админ', 'a@test.ru', UserRole::Admin);
        $this->foreman = $this->member('Прораб', 'f@test.ru', UserRole::Foreman);

        $this->project = Project::create(['name' => 'Школа №5, АПС', 'customer' => 'МОУ СОШ №5', 'contract_amount' => 1000000, 'status' => 'active']);
        $this->emp = Employee::create(['name' => 'Иванов И.И.', 'day_rate' => 3000]);
    }

    /** Текущая организация: в панели (Filament) и для моделей */
    protected function useOrganization(?Organization $org): void
    {
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($org, isQuiet: true);
    }

    /** Пользователь — участник организации с ролью (по умолчанию — текущей) */
    protected function member(string $name, string $email, UserRole $role, ?Organization $org = null): User
    {
        $user = User::create(['name' => $name, 'email' => $email, 'password' => 'secret123']);
        $user->forceFill(['email_verified_at' => now()])->save();
        ($org ?? $this->org)->addMember($user, $role);

        return $user;
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
