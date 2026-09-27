<?php

namespace Tests\Feature;

use App\Enums\AdvanceType;
use App\Enums\PaymentSource;
use App\Enums\UserRole;
use App\Filament\Resources\AdvanceResource;
use App\Filament\Resources\EmployeeResource;
use App\Filament\Resources\ExpenseCategoryResource;
use App\Filament\Resources\ExpenseResource;
use App\Filament\Resources\IncomeResource;
use App\Filament\Resources\PayoutResource;
use App\Filament\Resources\ProjectResource;
use App\Filament\Resources\UserResource;
use App\Filament\Resources\WorkLogResource;
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
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Filament\Resources\EmployeeResource\Widgets\EmployeeStats;
use App\Filament\Resources\ProjectResource\Widgets\ProjectPlanFact;
use App\Filament\Resources\ProjectResource\Widgets\ProjectStats;
use App\Filament\Widgets\ActiveProjects;
use App\Filament\Widgets\OverviewStats;
use App\Support\Money;
use Livewire\Livewire;
use Tests\TestCase;

class AccountingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $foreman;

    private Project $project;

    private Employee $emp;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(DatabaseSeeder::class);

        $this->admin = User::create(['name' => 'Админ', 'email' => 'a@test.ru', 'password' => 'secret123', 'role' => UserRole::Admin]);
        $this->foreman = User::create(['name' => 'Прораб', 'email' => 'f@test.ru', 'password' => 'secret123', 'role' => UserRole::Foreman]);

        $this->project = Project::create(['name' => 'Школа №5, АПС', 'customer' => 'МОУ СОШ №5', 'contract_amount' => 1000000, 'status' => 'active']);
        $this->emp = Employee::create(['name' => 'Иванов И.И.', 'day_rate' => 3000]);
    }

    private function cat(string $like): ExpenseCategory
    {
        return ExpenseCategory::where('name', 'like', "%{$like}%")->firstOrFail();
    }

    private function fillData(): void
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

    public function test_project_totals_and_plan_fact(): void
    {
        $this->fillData();
        $p = $this->project->fresh();

        $this->assertEquals(126000, $p->expensesTotal());
        $this->assertEquals(9500, $p->laborTotal()); // 2×3000 авто + 3500 вручную
        $this->assertEquals(135500, $p->costTotal());
        $this->assertEquals(300000, $p->receivedTotal());
        $this->assertEquals(864500, $p->contractMargin());
        $this->assertEquals(164500, $p->cashResult());
        $this->assertEquals(700000, $p->customerDebt());
        // прогноз: материалы max(300000,121000) + работа max(200000,9500) + ГСМ без плана 5000
        $this->assertEquals(505000, $p->forecastCost());
        $this->assertEquals(495000, $p->forecastProfit());

        $pf = $p->planFact()->keyBy('name');
        $this->assertEquals(300000, $pf['Материалы и оборудование']['plan']);
        $this->assertEquals(121000, $pf['Материалы и оборудование']['fact']);
        $this->assertEquals(9500, $pf['Работа (зарплата из журнала)']['fact']);
        $this->assertEquals(190500, $pf['Работа (зарплата из журнала)']['rest']);
        $this->assertEquals(5000, $pf['Транспорт и ГСМ']['fact']);
        $this->assertEquals(0, $pf['Транспорт и ГСМ']['plan']);
    }

    public function test_tax_percent_of_contract(): void
    {
        $this->fillData();
        $this->project->update(['tax_percent' => 6]);
        $p = $this->project->fresh();

        $this->assertEquals(60000, $p->taxTotal());          // 6% от 1 000 000
        $this->assertEquals(195500, $p->costTotal());        // 126000 + 9500 + 60000
        $this->assertEquals(804500, $p->contractMargin());
        $this->assertEquals(435000, $p->forecastProfit());   // 1 000 000 − (505000 + 60000)

        $pf = $p->planFact()->keyBy('name');
        $this->assertEquals(60000, $pf['Налоги (% от договора)']['fact']);
        $this->assertEquals(60000, $pf['Налоги (% от договора)']['plan']);
        $this->assertEquals(1, ExpenseCategory::where('is_tax', true)->count());

        $this->actingAs($this->admin);
        $this->get(ProjectResource::getUrl('index'))->assertOk()->assertSee(Money::fmt(195500));
        Livewire::test(ProjectStats::class, ['record' => $p])->assertSee(Money::fmt(804500))->assertSee('налоги');
    }

    public function test_employee_balances(): void
    {
        $this->fillData();
        $e = $this->emp->fresh();

        $this->assertEquals(9500, $e->accrued());
        $this->assertEquals(4000, $e->paid());
        $this->assertEquals(5500, $e->salaryDebt());
        $this->assertEquals(3000, $e->advanceBalance()); // 10000 − 2000 − 5000
        $this->assertNull(Expense::where('payment_source', 'cash')->first()->employee_id);
    }

    public function test_admin_can_open_every_page(): void
    {
        $this->fillData();
        $this->actingAs($this->admin);

        $this->get('/')->assertOk();
        Livewire::test(OverviewStats::class)->assertSee('Объектов в работе');
        Livewire::test(ActiveProjects::class)->assertSee('Школа №5, АПС')->assertSee(Money::fmt(135500))->assertSee(Money::fmt(495000));

        foreach ([ProjectResource::class, EmployeeResource::class, ExpenseResource::class, WorkLogResource::class,
            PayoutResource::class, AdvanceResource::class, IncomeResource::class, ExpenseCategoryResource::class, UserResource::class] as $r) {
            $this->get($r::getUrl('index'))->assertOk();
            $this->get($r::getUrl('create'))->assertOk();
        }

        $this->get(ProjectResource::getUrl('view', ['record' => $this->project]))->assertOk();
        Livewire::test(ProjectPlanFact::class, ['record' => $this->project])
            ->assertSee('Материалы и оборудование')->assertSee(Money::fmt(121000))->assertSee(Money::fmt(190500));
        Livewire::test(ProjectStats::class, ['record' => $this->project])
            ->assertSee('Прогноз прибыли')->assertSee(Money::fmt(495000))->assertSee(Money::fmt(864500));
        $this->get(ProjectResource::getUrl('edit', ['record' => $this->project]))->assertOk();
        $this->get(EmployeeResource::getUrl('view', ['record' => $this->emp]))->assertOk();
        Livewire::test(EmployeeStats::class, ['record' => $this->emp])->assertSee('Подотчёт на руках')->assertSee(Money::fmt(5500));
        $this->get(ExpenseResource::getUrl('edit', ['record' => Expense::first()]))->assertOk();
        $this->get(EmployeeResource::getUrl('index'))->assertOk()
            ->assertSee(\App\Support\Money::fmt(5500))   // долг по зарплате
            ->assertSee(\App\Support\Money::fmt(3000));  // подотчёт на руках
        $this->get(ProjectResource::getUrl('index'))->assertOk()
            ->assertSee(\App\Support\Money::fmt(135500)); // затраты по объекту
        $this->get(WorkLogResource::getUrl('edit', ['record' => WorkLog::first()]))->assertOk();
    }

    public function test_foreman_permissions(): void
    {
        $this->fillData();
        $this->actingAs($this->foreman);

        $this->get('/')->assertOk();
        $this->assertFalse(OverviewStats::canView());
        $this->assertFalse(ActiveProjects::canView());
        $this->get(ExpenseResource::getUrl('index'))->assertOk();
        $this->get(ExpenseResource::getUrl('create'))->assertOk();
        $this->get(WorkLogResource::getUrl('create'))->assertOk();
        $this->get(ProjectResource::getUrl('index'))->assertOk()->assertDontSee('Маржа по договору');

        $this->get(IncomeResource::getUrl('index'))->assertForbidden();
        $this->get(PayoutResource::getUrl('index'))->assertForbidden();
        $this->get(UserResource::getUrl('index'))->assertForbidden();
        $this->get(ExpenseResource::getUrl('edit', ['record' => Expense::first()]))->assertNotFound(); // чужая запись не видна
    }

    public function test_needs_attention(): void
    {
        $this->fillData();
        ProjectBudget::create(['project_id' => $this->project->id, 'expense_category_id' => $this->cat('ГСМ')->id, 'amount' => 1000]);

        $texts = \App\Support\Attention::items()->pluck('text')->implode(' | ');
        $this->assertStringContainsString('Перерасход по статье «Транспорт и ГСМ»', $texts);
        $this->assertStringContainsString('Не отчитался за '.Money::fmt(3000), $texts);

        $this->actingAs($this->admin);
        Livewire::test(\App\Filament\Widgets\NeedsAttention::class)->assertSee('Иванов И.И.')->assertSee('Школа №5, АПС');
    }

    public function test_negative_forecast_goes_to_attention(): void
    {
        $this->fillData();
        $this->project->update(['contract_amount' => 400000]); // план 500000 > договора
        $texts = \App\Support\Attention::items()->pluck('text')->implode(' | ');
        $this->assertStringContainsString('По прогнозу объект в убытке: '.Money::fmt(-105000), $texts);
    }

    public function test_quick_add_expense_from_project_page(): void
    {
        $this->actingAs($this->admin);
        Livewire::test(\App\Filament\Resources\ProjectResource\Pages\ViewProject::class, ['record' => $this->project->getKey()])
            ->callAction('addExpense', data: [
                'date' => '2026-09-10',
                'expense_category_id' => $this->cat('Материалы')->id,
                'amount' => 777,
                'payment_source' => 'cash',
                'description' => 'Кабель КПСнг',
            ])
            ->assertHasNoActionErrors();

        $e = Expense::where('amount', 777)->first();
        $this->assertNotNull($e);
        $this->assertEquals($this->project->id, $e->project_id);
        $this->assertEquals($this->admin->id, $e->created_by);
    }

    public function test_dadata_customer_lookup_fills_requisites(): void
    {
        config(['services.dadata.token' => 'test-token']);
        \Illuminate\Support\Facades\Http::fake([
            '*/suggest/party' => \Illuminate\Support\Facades\Http::response(['suggestions' => [[
                'value' => 'МОУ «СОШ № 5»',
                'data' => ['inn' => '6452000001', 'kpp' => '645201001', 'ogrn' => '1026402000001',
                    'address' => ['value' => 'г Саратов, ул Примерная, д 1', 'unrestricted_value' => '410000, Саратовская обл, г Саратов, ул Примерная, д 1'],
                    'state' => ['status' => 'ACTIVE']],
            ]]]),
            '*/suggest/address' => \Illuminate\Support\Facades\Http::response(['suggestions' => [['value' => 'г Саратов, ул Московская, д 10']]]),
        ]);

        $options = \App\Support\DaData::parties('6452000001');
        $this->assertArrayHasKey('6452000001-645201001', $options);
        $this->assertStringContainsString('ИНН 6452000001', $options['6452000001-645201001']);
        $this->assertSame(['г Саратов, ул Московская, д 10' => 'г Саратов, ул Московская, д 10'], \App\Support\DaData::addresses('Саратов Москов'));

        $this->actingAs($this->admin);
        Livewire::test(\App\Filament\Resources\ProjectResource\Pages\CreateProject::class)
            ->fillForm(['customer_lookup' => '6452000001-645201001', 'address_lookup' => 'г Саратов, ул Московская, д 10'])
            ->assertFormSet([
                'customer' => 'МОУ «СОШ № 5»',
                'customer_inn' => '6452000001',
                'customer_kpp' => '645201001',
                'customer_ogrn' => '1026402000001',
                'address' => 'г Саратов, ул Московская, д 10',
            ])
            ->fillForm(['name' => 'СОШ №5 — оповещение', 'contract_amount' => 500000])
            ->call('create')
            ->assertHasNoFormErrors();

        $p = Project::where('customer_inn', '6452000001')->first();
        $this->assertNotNull($p);
        $this->assertSame('410000, Саратовская обл, г Саратов, ул Примерная, д 1', $p->customer_address);
    }

    public function test_dadata_disabled_without_token(): void
    {
        config(['services.dadata.token' => null]);
        $this->assertFalse(\App\Support\DaData::enabled());
        $this->assertSame([], \App\Support\DaData::parties('6452000001'));
        $this->actingAs($this->admin)->get(ProjectResource::getUrl('create'))->assertOk()->assertDontSee('Найти заказчика');
    }

    public function test_inactive_user_cannot_enter(): void
    {
        $this->foreman->update(['is_active' => false]);
        $this->actingAs($this->foreman)->get('/')->assertForbidden();
    }
}
