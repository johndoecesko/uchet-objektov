<?php

namespace Tests\Feature;

use App\Enums\OrganizationStatus;
use App\Enums\PaymentSource;
use App\Enums\UserRole;
use App\Filament\Resources\ActivityResource\Pages\ListActivities;
use App\Filament\Resources\AdvanceResource;
use App\Filament\Resources\AdvanceResource\Pages\ListAdvances;
use App\Filament\Resources\EmployeeResource;
use App\Filament\Resources\EmployeeResource\Pages\ListEmployees;
use App\Filament\Resources\ExpenseCategoryResource\Pages\ListExpenseCategories;
use App\Filament\Resources\ExpenseResource;
use App\Filament\Resources\ExpenseResource\Pages\CreateExpense;
use App\Filament\Resources\ExpenseResource\Pages\EditExpense;
use App\Filament\Resources\ExpenseResource\Pages\ListExpenses;
use App\Filament\Resources\IncomeResource;
use App\Filament\Resources\IncomeResource\Pages\ListIncomes;
use App\Filament\Resources\MemberResource\Pages\ListMembers;
use App\Filament\Resources\PayoutResource;
use App\Filament\Resources\PayoutResource\Pages\ListPayouts;
use App\Filament\Resources\ProjectResource;
use App\Filament\Resources\ProjectResource\Pages\ListProjects;
use App\Filament\Resources\WorkLogResource;
use App\Filament\Resources\WorkLogResource\Pages\ListWorkLogs;
use App\Filament\Support\Fields;
use App\Filament\Widgets\ActiveProjects;
use App\Filament\Widgets\NeedsAttention;
use App\Filament\Widgets\OverviewStats;
use App\Models\Activity;
use App\Models\Advance;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Income;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Payout;
use App\Models\Project;
use App\Models\ProjectBudget;
use App\Models\Setting;
use App\Models\User;
use App\Models\WorkLog;
use App\Reports\OwnerReport;
use App\Reports\PayslipReport;
use App\Reports\ProjectReport;
use App\Support\Attention;
use App\Support\CurrentOrganization;
use App\Support\Money;
use App\Support\OrganizationMismatch;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use OpenSpout\Reader\XLSX\Reader;
use Tests\Feature\Concerns\SeedsAccounting;
use Tests\TestCase;

/**
 * Изоляция организаций: пользователь организации A не видит и не меняет данные организации B
 * ни списками, ни по прямой ссылке, ни через формы, отчёты, виджеты, историю, файлы.
 */
class TenancyIsolationTest extends TestCase
{
    use RefreshDatabase, SeedsAccounting {
        SeedsAccounting::setUp as seedAccounting;
    }

    private Organization $orgB;

    private User $adminB;

    private Project $projectB;

    private Employee $empB;

    private Expense $expenseB;

    private string $fileB;

    protected function setUp(): void
    {
        $this->seedAccounting();
        Storage::fake('local');
        $this->fillData();

        // Организация B со своими данными (названия и суммы — заметные, чтобы ловить утечки)
        $this->orgB = Organization::create(['name' => 'ИП Чужой', 'slug' => 'chuzhoy']);
        $this->adminB = $this->member('Чужой Админ', 'b@test.ru', UserRole::Admin, $this->orgB);
        $this->inOrg($this->orgB, function () {
            $this->actingAs($this->adminB);
            ExpenseCategory::where('name', 'Прочее')->update(['name' => 'Тайная статья Б']);
            $this->projectB = Project::create(['name' => 'Секретный объект Б', 'customer' => 'Заказчик Б', 'contract_amount' => 7777777, 'status' => 'active']);
            $this->empB = Employee::create(['name' => 'Сотрудник Б', 'day_rate' => 9999]);
            $this->fileB = 'receipts/'.$this->orgB->id.'/b-receipt.jpg';
            Storage::disk('local')->put($this->fileB, 'B-JPEG');
            $this->expenseB = Expense::create(['date' => '2026-09-02', 'project_id' => $this->projectB->id, 'expense_category_id' => ExpenseCategory::where('name', 'like', 'Материалы%')->value('id'),
                'amount' => 654321, 'supplier' => 'Поставщик Б', 'payment_source' => PaymentSource::Advance, 'employee_id' => $this->empB->id, 'attachments' => [$this->fileB]]);
            ProjectBudget::create(['project_id' => $this->projectB->id, 'expense_category_id' => ExpenseCategory::laborId(), 'amount' => 1]);
            WorkLog::create(['date' => '2026-09-02', 'project_id' => $this->projectB->id, 'employee_id' => $this->empB->id, 'days' => 1, 'rate' => 9999, 'work' => 'Работа Б']);
            Income::create(['date' => '2026-09-02', 'project_id' => $this->projectB->id, 'amount' => 5555555, 'type' => 'advance', 'method' => 'bank', 'notes' => 'Поступление Б']);
            Payout::create(['date' => '2026-09-02', 'employee_id' => $this->empB->id, 'amount' => 4444, 'method' => 'card', 'notes' => 'Выплата Б']);
            Advance::create(['date' => '2026-08-01', 'employee_id' => $this->empB->id, 'type' => 'issue', 'amount' => 888888, 'method' => 'cash', 'notes' => 'Подотчёт Б']);
            Setting::put('attention.advance_days', 1);
            Expense::create(['date' => '2026-09-03', 'project_id' => $this->projectB->id, 'expense_category_id' => ExpenseCategory::where('name', 'like', 'Материалы%')->value('id'), 'amount' => 111, 'payment_source' => PaymentSource::Bank])->delete();
        });
        auth()->forgetUser();
    }

    private function inOrg(Organization $org, callable $fn): void
    {
        $prev = Filament::getTenant();
        $this->useOrganization($org);
        try {
            $fn();
        } finally {
            $this->useOrganization($prev);
        }
    }

    /** @return list<string> строки всех листов книги */
    private function xlsxText(string $path): string
    {
        $reader = new Reader;
        $reader->open($path);
        $out = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $out[] = implode(' | ', array_map(fn ($v) => $v instanceof \DateTimeInterface ? $v->format('d.m.Y') : (string) $v, $row->toArray()));
            }
        }
        $reader->close();

        return implode("\n", $out);
    }

    public function test_every_model_is_scoped_to_current_organization(): void
    {
        foreach ([Project::class, Employee::class, Expense::class, ExpenseCategory::class, WorkLog::class, Income::class, Payout::class, Advance::class, ProjectBudget::class, Membership::class] as $class) {
            $ids = $class::query()->pluck('organization_id')->unique()->values()->all();
            $this->assertSame([$this->org->id], $ids, $class.' видит чужие строки');
        }
        $this->assertSame(9, ExpenseCategory::count(), 'свой справочник статей');
        $this->assertSame(0, Expense::onlyTrashed()->count(), 'корзина B не видна');
        $this->assertNull(Expense::find($this->expenseB->id));
        $this->assertNull(Project::find($this->projectB->id));
        $this->assertSame([$this->org->id], Activity::query()->pluck('organization_id')->unique()->values()->all());
    }

    public function test_lists_show_only_own_records(): void
    {
        $this->actingAs($this->admin);
        $b = fn (string $class) => $class::acrossOrganizations()->where('organization_id', $this->orgB->id)->get();
        $a = fn (string $class) => $class::query()->get();

        Livewire::test(ListProjects::class)->filterTable('status', null)->assertCanSeeTableRecords($a(Project::class))->assertCanNotSeeTableRecords($b(Project::class))->assertDontSee('Секретный объект Б');
        Livewire::test(ListExpenses::class)->assertCanSeeTableRecords($a(Expense::class))->assertCanNotSeeTableRecords($b(Expense::class))->assertDontSee('Поставщик Б');
        Livewire::test(ListExpenses::class)->filterTable('trashed', false)->assertCanNotSeeTableRecords(Expense::acrossOrganizations()->onlyTrashed()->where('organization_id', $this->orgB->id)->get());
        Livewire::test(ListWorkLogs::class)->assertCanSeeTableRecords($a(WorkLog::class))->assertCanNotSeeTableRecords($b(WorkLog::class))->assertDontSee('Работа Б');
        Livewire::test(ListIncomes::class)->assertCanNotSeeTableRecords($b(Income::class))->assertDontSee('Поступление Б');
        Livewire::test(ListPayouts::class)->assertCanNotSeeTableRecords($b(Payout::class))->assertDontSee('Выплата Б');
        Livewire::test(ListAdvances::class)->assertCanNotSeeTableRecords($b(Advance::class))->assertDontSee('Подотчёт Б');
        Livewire::test(ListEmployees::class)->assertCanSeeTableRecords($a(Employee::class))->assertCanNotSeeTableRecords($b(Employee::class))->assertDontSee('Сотрудник Б');
        Livewire::test(ListExpenseCategories::class)->assertCanNotSeeTableRecords($b(ExpenseCategory::class))->assertDontSee('Тайная статья Б');
        Livewire::test(ListMembers::class)->assertCanNotSeeTableRecords($b(Membership::class))->assertDontSee('b@test.ru');
        Livewire::test(ListActivities::class)->assertCanNotSeeTableRecords(Activity::acrossOrganizations()->where('organization_id', $this->orgB->id)->get())
            ->assertDontSee('Секретный объект Б')->assertDontSee('Чужой Админ');
    }

    public function test_direct_urls_of_other_organization_are_not_found(): void
    {
        $this->actingAs($this->admin);

        $this->get(ProjectResource::getUrl('view', ['record' => $this->projectB]))->assertNotFound();
        $this->get(ProjectResource::getUrl('edit', ['record' => $this->projectB]))->assertNotFound();
        $this->get(ExpenseResource::getUrl('edit', ['record' => $this->expenseB]))->assertNotFound();
        $this->get(EmployeeResource::getUrl('view', ['record' => $this->empB]))->assertNotFound();
        $this->get(WorkLogResource::getUrl('edit', ['record' => WorkLog::acrossOrganizations()->where('organization_id', $this->orgB->id)->first()]))->assertNotFound();
        $this->get(IncomeResource::getUrl('edit', ['record' => Income::acrossOrganizations()->where('organization_id', $this->orgB->id)->first()]))->assertNotFound();
        $this->get(PayoutResource::getUrl('edit', ['record' => Payout::acrossOrganizations()->where('organization_id', $this->orgB->id)->first()]))->assertNotFound();
        $this->get(AdvanceResource::getUrl('edit', ['record' => Advance::acrossOrganizations()->where('organization_id', $this->orgB->id)->first()]))->assertNotFound();

        // и сама организация B по своему адресу — тоже 404 (не участник)
        $this->get('/app/chuzhoy')->assertNotFound();
        $this->get('/app/chuzhoy/projects/'.$this->projectB->id)->assertNotFound();
        // ресурс A с записью B в адресе
        $this->get('/app/montazh/projects/'.$this->projectB->id)->assertNotFound();
        $this->get('/app/montazh/expenses/'.$this->expenseB->id.'/edit')->assertNotFound();
    }

    public function test_cannot_edit_delete_or_restore_other_organization_records(): void
    {
        $this->actingAs($this->admin);
        $trashedB = Expense::acrossOrganizations()->onlyTrashed()->where('organization_id', $this->orgB->id)->first();

        $this->assertThrows(fn () => Livewire::test(EditExpense::class, ['record' => $this->expenseB->getKey()]));

        // действие над чужой строкой таблицы: Filament не находит запись в своём (ограниченном) запросе
        $this->assertThrows(fn () => Livewire::test(ListExpenses::class)->callAction(TestAction::make(DeleteAction::class)->table($this->expenseB)));
        $this->assertThrows(fn () => Livewire::test(ListExpenses::class)->filterTable('trashed', false)->callAction(TestAction::make(RestoreAction::class)->table($trashedB)));

        $this->assertNull(Expense::acrossOrganizations()->find($this->expenseB->id)->deleted_at, 'чужой расход не удалён');
        $this->assertNotNull(Expense::acrossOrganizations()->withTrashed()->find($trashedB->id)->deleted_at, 'чужой расход не восстановлен');
    }

    public function test_forms_reject_other_organization_references(): void
    {
        $this->actingAs($this->admin);
        $catB = ExpenseCategory::acrossOrganizations()->where('organization_id', $this->orgB->id)->where('name', 'like', 'Материалы%')->first();

        Livewire::test(CreateExpense::class)
            ->fillForm(['date' => '2026-09-10', 'project_id' => $this->projectB->id, 'expense_category_id' => $this->cat('Материалы')->id, 'amount' => 10, 'payment_source' => 'bank'])
            ->call('create')->assertHasFormErrors(['project_id']);
        Livewire::test(CreateExpense::class)
            ->fillForm(['date' => '2026-09-10', 'project_id' => $this->project->id, 'expense_category_id' => $catB->id, 'amount' => 10, 'payment_source' => 'bank'])
            ->call('create')->assertHasFormErrors(['expense_category_id']);
        Livewire::test(CreateExpense::class)
            ->fillForm(['date' => '2026-09-10', 'project_id' => $this->project->id, 'expense_category_id' => $this->cat('Материалы')->id, 'amount' => 10, 'payment_source' => 'advance', 'employee_id' => $this->empB->id])
            ->call('create')->assertHasFormErrors(['employee_id']);
        $this->assertSame(0, Expense::where('amount', 10)->count());

        // выпадающие списки — только свои объекты, статьи, сотрудники, участники
        Livewire::test(CreateExpense::class)->assertFormFieldExists('project_id', fn ($f) => ! array_key_exists($this->projectB->id, $f->getOptions()) && array_key_exists($this->project->id, $f->getOptions()));
        Livewire::test(CreateExpense::class)->assertFormFieldExists('expense_category_id', fn ($f) => ! array_key_exists($catB->id, $f->getOptions()));
        $this->assertArrayNotHasKey($this->adminB->id, Fields::memberOptions());
        $this->assertArrayHasKey($this->foreman->id, Fields::memberOptions());
    }

    public function test_models_refuse_cross_organization_links(): void
    {
        $this->expectException(OrganizationMismatch::class);
        Expense::create(['date' => '2026-09-10', 'project_id' => $this->projectB->id, 'expense_category_id' => $this->cat('Материалы')->id, 'amount' => 1, 'payment_source' => 'bank']);
    }

    public function test_employee_can_link_only_member_of_same_organization(): void
    {
        $this->emp->update(['user_id' => $this->foreman->id]);
        $this->assertSame($this->foreman->id, $this->emp->fresh()->user_id);

        $this->expectException(OrganizationMismatch::class);
        $this->emp->update(['user_id' => $this->adminB->id]);
    }

    public function test_creating_without_organization_is_an_error(): void
    {
        $this->useOrganization(null);
        $this->expectException(OrganizationMismatch::class);
        Project::create(['name' => 'Ничей', 'contract_amount' => 0]);
    }

    public function test_global_search_finds_only_own_records(): void
    {
        $this->actingAs($this->admin);
        $results = Filament::getGlobalSearchProvider()->getResults('объект');
        $titles = collect($results?->getCategories() ?? [])->flatten(1)->map(fn ($r) => (string) $r->title)->implode('|');
        $this->assertStringNotContainsString('Секретный объект Б', $titles);

        $results = Filament::getGlobalSearchProvider()->getResults('Сотрудник Б');
        $this->assertTrue($results === null || collect($results->getCategories())->flatten(1)->isEmpty());
    }

    public function test_reports_include_only_own_data(): void
    {
        $owner = $this->xlsxText((new OwnerReport(Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31')))->build()->save());
        $this->assertStringContainsString('Школа №5, АПС', $owner);
        $this->assertStringNotContainsString('Секретный объект Б', $owner);
        $this->assertStringNotContainsString('7777777', $owner);

        $project = $this->xlsxText((new ProjectReport($this->project))->build()->save());
        $this->assertStringNotContainsString('Поставщик Б', $project);
        $this->assertStringNotContainsString('Тайная статья Б', $project);

        $payslip = $this->xlsxText((new PayslipReport($this->emp, Carbon::parse('2026-01-01'), Carbon::parse('2026-12-31')))->build()->save());
        $this->assertStringContainsString('Иванов И.И.', $payslip);
        $this->assertStringNotContainsString('Секретный объект Б', $payslip);
    }

    public function test_dashboard_widgets_and_attention_show_only_own_data(): void
    {
        $this->travelTo('2026-09-10');
        $this->actingAs($this->admin);

        $texts = Attention::items()->pluck('title')->implode('|');
        $this->assertStringNotContainsString('Сотрудник Б', $texts);
        $this->assertStringNotContainsString('Секретный объект Б', $texts);
        $this->assertSame(14, Attention::advanceDays(), 'настройки B не влияют на A');

        Livewire::test(OverviewStats::class)->assertSee('Объектов в работе')->assertDontSee(Money::fmt(5555555));
        Livewire::test(ActiveProjects::class)->assertSee('Школа №5, АПС')->assertDontSee('Секретный объект Б');
        Livewire::test(NeedsAttention::class)->assertDontSee('Сотрудник Б');
        $this->get('/app/montazh')->assertOk()->assertDontSee('Секретный объект Б')->assertDontSee('ИП Чужой');
    }

    public function test_settings_are_per_organization(): void
    {
        Setting::put('attention.low_margin', 33);
        $this->assertSame(33, (int) Setting::get('attention.low_margin'));
        $this->inOrg($this->orgB, function () {
            $this->assertSame(1, (int) Setting::get('attention.advance_days'));
            $this->assertSame(10, (int) Setting::get('attention.low_margin'));
        });
        $this->assertSame(14, (int) Setting::get('attention.advance_days'));
    }

    public function test_attachments_of_other_organization_cannot_be_downloaded(): void
    {
        $this->actingAs($this->admin);

        // без подписи файл не отдаётся никому
        $this->get('/storage/'.$this->fileB)->assertStatus(403);

        // подставить в форму своего расхода чужой путь — ссылка на файл не выдаётся
        $own = Expense::where('amount', 120000)->first();
        $own->update(['attachments' => ['receipts/'.$this->org->id.'/a.jpg']]);
        Storage::disk('local')->put('receipts/'.$this->org->id.'/a.jpg', 'A-JPEG');

        $page = Livewire::test(EditExpense::class, ['record' => $own->getKey()]);
        $files = $page->instance()->callSchemaComponentMethod('form.attachments', 'getUploadedFiles');
        $this->assertNotEmpty(array_filter($files ?? []), 'свой файл открывается');

        $page->set('data.attachments', ['x' => $this->fileB]);
        $files = $page->instance()->callSchemaComponentMethod('form.attachments', 'getUploadedFiles');
        $this->assertEmpty(array_filter($files ?? []), 'чужой путь не получает ссылку');

        // и сохранить чужой путь в свою запись нельзя
        $page->call('save')->assertHasFormErrors(['attachments']);
        $this->assertSame(['receipts/'.$this->org->id.'/a.jpg'], $own->fresh()->attachments);
    }

    public function test_new_uploads_go_to_organization_folder(): void
    {
        $this->assertSame('receipts/'.$this->org->id, Fields::organizationDirectory('receipts'));
        $this->assertSame('documents/'.$this->org->id.'/incomes', Fields::organizationDirectory('documents/incomes'));
    }

    public function test_user_in_two_organizations_sees_each_with_own_role(): void
    {
        // админ A — прораб в B
        $this->orgB->addMember($this->admin, UserRole::Foreman);

        $this->actingAs($this->admin);
        $this->get('/app/montazh/incomes')->assertOk();
        $this->get('/app/chuzhoy')->assertOk()->assertSee('Мои расходы за месяц');
        $this->get('/app/chuzhoy/incomes')->assertForbidden();
        $this->get('/app/chuzhoy/projects')->assertOk()->assertSee('Секретный объект Б')->assertDontSee('Школа №5, АПС');

        $this->assertSame(UserRole::Admin, $this->admin->roleIn($this->org));
        $this->assertSame(UserRole::Foreman, $this->admin->roleIn($this->orgB));
        $this->assertCount(2, $this->admin->getTenants(Filament::getPanel('admin')));
    }

    public function test_inactive_membership_gives_no_access(): void
    {
        $this->orgB->addMember($this->admin, UserRole::Manager, active: false);
        $this->actingAs($this->admin)->get('/app/chuzhoy')->assertNotFound();
        $this->assertCount(1, $this->admin->getTenants(Filament::getPanel('admin')));
    }

    public function test_blocked_organization_shows_message(): void
    {
        $this->orgB->update(['status' => OrganizationStatus::Blocked]);
        $this->actingAs($this->adminB)->get('/app/chuzhoy')->assertForbidden()->assertSee('заблокирована');
        $this->get('/app/chuzhoy/projects')->assertForbidden();
    }

    public function test_current_organization_helper_restores_previous(): void
    {
        $current = app(CurrentOrganization::class);
        $this->assertSame($this->org->id, $current->id());
        $current->run($this->orgB, fn () => $this->assertSame($this->orgB->id, $current->id()));
        $this->assertSame($this->org->id, $current->id());
    }
}
