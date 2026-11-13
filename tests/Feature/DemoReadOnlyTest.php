<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Auth\EditProfile;
use App\Filament\Pages\Settings;
use App\Filament\Platform\Resources\OrganizationResource\Pages\ListOrganizations;
use App\Filament\Platform\Resources\UserResource\Pages\ListUsers;
use App\Filament\Platform\Widgets\PlatformStats;
use App\Filament\Resources\EmployeeResource\Pages\ViewEmployee;
use App\Filament\Resources\ExpenseCategoryResource\Pages\ListExpenseCategories;
use App\Filament\Resources\ExpenseResource\Pages\ListExpenses;
use App\Filament\Resources\MemberResource\Pages\ListMembers;
use App\Filament\Resources\MemberResource\Widgets\PendingInvitations;
use App\Filament\Resources\ProjectResource\Pages\ListProjects;
use App\Filament\Resources\ProjectResource\Pages\ViewProject;
use App\Models\Advance;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Income;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Payout;
use App\Models\Project;
use App\Models\Setting;
use App\Models\User;
use App\Models\WorkLog;
use App\Support\Demo\Demo;
use App\Support\Demo\DemoReadOnly;
use Filament\Actions\CreateAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\Feature\Concerns\SeedsAccounting;
use Tests\Feature\Concerns\UsesDemo;
use Tests\TestCase;

/** Демо только для чтения: ни по прямому адресу, ни через действия Livewire, ни через модели ничего не записать */
class DemoReadOnlyTest extends TestCase
{
    use RefreshDatabase, UsesDemo;
    use SeedsAccounting { setUp as seedAccounting; }

    protected function setUp(): void
    {
        $this->seedAccounting();
        $this->fillData();
        $this->makeDemo();
    }

    /** Строк в базе: все таблицы с данными, участники, учётки, организации */
    private function rowCounts(): array
    {
        $out = [];
        foreach (['projects', 'project_budgets', 'employees', 'expenses', 'expense_categories', 'work_logs', 'payouts', 'advances',
            'incomes', 'invitations', 'settings', 'activity_log', 'organization_user', 'users', 'organizations', 'social_accounts'] as $t) {
            $out[$t] = DB::table($t)->count();
        }
        $out['sorts'] = DB::table('expense_categories')->orderBy('id')->pluck('sort')->implode(',');
        $out['deleted'] = DB::table('expenses')->whereNotNull('deleted_at')->count();

        return $out;
    }

    /**
     * Попытка выполнить действие «в обход кнопки» — сырыми вызовами Livewire, как сделал бы злоумышленник:
     * mountAction (с контекстом строки таблицы или массового действия) → данные формы → callMountedAction.
     * Скрытое или запрещённое действие не монтируется; если бы смонтировалось — запись остановит модель.
     */
    private function attempt($component, string $name, array $context = [], array $data = []): void
    {
        $component->call('mountAction', $name, [], $context);
        if ($data !== [] && $component->get('mountedActions') !== []) {
            $component->set('mountedActions.0.data', $data);
        }
        $component->call('callMountedAction');
        $this->assertContains($component->get('mountedActions'), [[], null]);
    }

    private function demoRecord(string $class)
    {
        return $class::acrossOrganizations()->where('organization_id', $this->demo->id)->orderBy('id')->firstOrFail();
    }

    public function test_create_and_edit_pages_are_forbidden(): void
    {
        $this->enterDemo();
        $before = $this->rowCounts();

        foreach (['projects' => Project::class, 'expenses' => Expense::class, 'work-logs' => WorkLog::class, 'incomes' => Income::class,
            'payouts' => Payout::class, 'advances' => Advance::class, 'employees' => Employee::class, 'expense-categories' => ExpenseCategory::class] as $slug => $class) {
            $this->get("/app/demo/{$slug}")->assertOk();
            $this->get("/app/demo/{$slug}/create")->assertForbidden();
            $this->get("/app/demo/{$slug}/".$this->demoRecord($class)->id.'/edit')->assertForbidden();
        }
        $this->get('/app/demo/members/'.$this->demoRecord(Membership::class)->id.'/edit')->assertForbidden();

        // всё остальное — открывается
        foreach (['', '/projects/'.$this->demoRecord(Project::class)->id, '/employees/'.$this->demoRecord(Employee::class)->id,
            '/members', '/history', '/settings', '/about'] as $path) {
            $this->get('/app/demo'.$path)->assertOk();
        }

        $this->assertSame($before, $this->rowCounts());
    }

    public function test_new_organization_profile_pages_and_platform_are_closed(): void
    {
        $this->enterDemo();
        $this->get('/app/new')->assertRedirect('/app/demo');
        $this->get('/app/demo/profile')->assertRedirect('/app/demo');
        $this->get('/app/profile')->assertRedirect('/app/demo');
        $this->get('/platform')->assertForbidden();
        $this->assertFalse($this->demoAdmin->can('create', Organization::class));
        $this->assertFalse($this->demoAdmin->can('update', $this->demo));
    }

    public function test_header_actions_are_hidden_and_do_nothing(): void
    {
        $this->enterDemo();
        $before = $this->rowCounts();
        $project = $this->demoRecord(Project::class);

        Livewire::test(ListProjects::class)->assertActionHidden(CreateAction::class)->assertActionVisible('ownerReport');
        Livewire::test(ListExpenses::class)->assertActionHidden(CreateAction::class);
        $view = Livewire::test(ViewProject::class, ['record' => $project->getKey()])
            ->assertActionHidden('addExpense')->assertActionHidden('addWork')->assertActionHidden('addIncome')
            ->assertActionHidden(EditAction::class)->assertActionVisible('exportXlsx');
        $this->attempt($view, 'addExpense', data: ['date' => now()->toDateString(), 'expense_category_id' => ExpenseCategory::query()->value('id'), 'amount' => 1, 'payment_source' => 'cash']);
        $this->attempt($view, 'addIncome', data: ['date' => now()->toDateString(), 'amount' => 1, 'type' => 'advance', 'method' => 'bank']);
        $members = Livewire::test(ListMembers::class)->assertActionHidden('inviteLink')->assertActionHidden('inviteEmail');
        $this->attempt($members, 'inviteLink', data: ['role' => 'admin']);
        $this->attempt($members, 'inviteEmail', data: ['email' => 'x@test.ru', 'role' => 'admin']);
        $this->attempt(Livewire::test(ListProjects::class), 'create');

        $this->assertSame($before, $this->rowCounts());
    }

    public function test_row_bulk_and_trash_actions_are_blocked(): void
    {
        $this->enterDemo();
        $before = $this->rowCounts();
        $expense = $this->demoRecord(Expense::class);

        $list = Livewire::test(ListExpenses::class)
            ->assertActionHidden(TestAction::make(EditAction::class)->table($expense))
            ->assertActionHidden(TestAction::make(DeleteAction::class)->table($expense));
        $this->attempt($list, 'delete', ['table' => true, 'recordKey' => (string) $expense->getKey()]);
        $this->attempt($list, 'forceDelete', ['table' => true, 'recordKey' => (string) $expense->getKey()]);
        $list->selectTableRecords([$expense->getKey()]);
        $this->attempt($list, 'delete', ['table' => true, 'bulk' => true]);
        $this->attempt($list, 'forceDelete', ['table' => true, 'bulk' => true]);

        // корзина: запись, удалённая при пересоздании «вручную», не восстанавливается
        DB::table('expenses')->where('id', $expense->id)->update(['deleted_at' => now()]);
        $before['deleted']++;
        $trash = Livewire::test(ListExpenses::class)->filterTable('trashed', false)
            ->assertActionHidden(TestAction::make(RestoreAction::class)->table($expense));
        $this->attempt($trash, 'restore', ['table' => true, 'recordKey' => (string) $expense->getKey()]);

        $this->assertSame($before, $this->rowCounts());
        $this->assertNotNull(Expense::withTrashed()->find($expense->id)?->deleted_at);
    }

    public function test_pending_invitation_actions_are_blocked(): void
    {
        $this->enterDemo();
        $invite = $this->demoRecord(Invitation::class);

        $widget = Livewire::test(PendingInvitations::class)
            ->assertActionHidden(TestAction::make('link')->table($invite))
            ->assertActionHidden(TestAction::make(DeleteAction::class)->table($invite));
        $this->attempt($widget, 'delete', ['table' => true, 'recordKey' => (string) $invite->getKey()]);
        $this->attempt($widget, 'link', ['table' => true, 'recordKey' => (string) $invite->getKey()]);
        $widget->assertNotNotified('Ссылка-приглашение (Прораб / инженер)');
        $this->assertNotNull($invite->fresh());
    }

    public function test_reorder_does_not_change_sort(): void
    {
        $this->enterDemo();
        $before = ExpenseCategory::orderBy('id')->pluck('sort', 'id')->all();

        Livewire::test(ListExpenseCategories::class)->call('reorderTable', array_map('strval', array_reverse(array_keys($before))));
        $this->assertSame($before, ExpenseCategory::orderBy('id')->pluck('sort', 'id')->all());
    }

    public function test_settings_and_profile_are_read_only(): void
    {
        $this->enterDemo();
        Livewire::test(Settings::class)->assertFormFieldIsDisabled('advance_days')
            ->set('data.advance_days', 99)->call('save')->assertForbidden();
        $this->assertSame(0, Setting::acrossOrganizations()->where('organization_id', $this->demo->id)->count());

        Livewire::test(EditProfile::class)->assertRedirect('/app/demo');
        $this->assertSame('Анна Директорова', $this->demoAdmin->fresh()->name);
    }

    public function test_file_upload_endpoint_is_forbidden(): void
    {
        $this->enterDemo();
        $this->post('/livewire/upload-file', ['files' => [UploadedFile::fake()->image('check.jpg')]])->assertForbidden();
    }

    public function test_models_refuse_any_write_into_demo_even_without_user(): void
    {
        auth()->forgetUser();
        $project = $this->demoRecord(Project::class);
        $this->useOrganization($this->demo);

        $this->assertThrows(fn () => Project::create(['name' => 'Взлом', 'contract_amount' => 1]), DemoReadOnly::class);
        $this->assertThrows(fn () => $project->update(['name' => 'Взлом']), DemoReadOnly::class);
        $this->assertThrows(fn () => $project->delete(), DemoReadOnly::class);
        $this->assertThrows(fn () => $this->demoRecord(Expense::class)->forceDelete(), DemoReadOnly::class);
        $this->assertThrows(fn () => $project->budgets()->first()->update(['amount' => 1]), DemoReadOnly::class);
        $this->assertThrows(fn () => Setting::put('attention.advance_days', 1), DemoReadOnly::class);
        $this->assertThrows(fn () => $this->demo->update(['name' => 'Взлом']), DemoReadOnly::class);
        $this->assertThrows(fn () => $this->demoAdmin->update(['name' => 'Взлом']), DemoReadOnly::class);
        $this->assertThrows(fn () => $this->demoAdmin->forceFill(['password' => 'new-password'])->save(), DemoReadOnly::class);
        $this->assertThrows(fn () => $this->demo->addMember($this->admin, UserRole::Admin));

        // признак демо вручную не ставится и не снимается
        $this->assertThrows(fn () => $this->org->forceFill(['is_demo' => true])->save(), DemoReadOnly::class);
        $this->assertThrows(fn () => $this->admin->forceFill(['is_demo' => true])->save(), DemoReadOnly::class);
        $this->assertFalse((bool) $this->org->fresh()->is_demo);

        // app:organization тоже не переименует демо
        $this->assertThrows(fn () => $this->artisan('app:organization demo --name=Взлом')->run(), DemoReadOnly::class);
        $this->assertSame('ООО «Демо-Монтаж»', $this->demo->fresh()->name);
        $this->assertNotSame('Взлом', $project->fresh()->name);
    }

    public function test_demo_user_cannot_write_into_another_organization(): void
    {
        $this->enterDemo();
        $this->useOrganization($this->org);
        $this->assertThrows(fn () => Project::create(['name' => 'Чужое', 'contract_amount' => 1]), DemoReadOnly::class);
        $this->assertThrows(fn () => $this->project->update(['name' => 'Чужое']), DemoReadOnly::class);
        $this->assertThrows(fn () => $this->admin->update(['name' => 'Чужое']), DemoReadOnly::class);
        $this->assertSame('Школа №5, АПС', $this->project->fresh()->name);
    }

    public function test_exports_are_allowed_but_limited(): void
    {
        $this->assertSame(10, config('demo.export_per_hour'));
        config(['demo.export_per_hour' => 2]); // каждая выгрузка — несколько МБ памяти теста
        $this->enterDemo();
        $project = $this->demoRecord(Project::class);
        for ($i = 1; $i <= 2; $i++) {
            Livewire::test(ViewProject::class, ['record' => $project->getKey()])->callAction('exportXlsx')->assertFileDownloaded();
        }
        Livewire::test(ViewProject::class, ['record' => $project->getKey()])->call('mountAction', 'exportXlsx')->assertStatus(429); // без формы действие выполняется сразу при монтировании

        Livewire::test(ViewEmployee::class, ['record' => $this->demoRecord(Employee::class)->getKey()])->assertActionVisible('payslip');
    }

    public function test_platform_hides_blocking_and_excludes_demo_from_stats(): void
    {
        $platformAdmin = $this->admin;
        $platformAdmin->forceFill(['is_platform_admin' => true])->save();
        $this->actingAs($platformAdmin);
        Filament::setCurrentPanel(Filament::getPanel('platform'));
        Filament::setTenant(null, isQuiet: true);

        Livewire::test(ListOrganizations::class)
            ->assertActionHidden(TestAction::make('block')->table($this->demo))
            ->assertActionVisible(TestAction::make('block')->table($this->org))
            ->assertSee('Демо');
        Livewire::test(ListUsers::class)->assertActionHidden(TestAction::make('block')->table($this->demoAdmin));

        Livewire::test(PlatformStats::class)->assertSee('Демо за 7 дней');
        $stats = (fn () => $this->getStats())->call(new PlatformStats);
        $this->assertSame('1', $stats[0]->getValue(), 'одна настоящая организация — демо не в счёт');
        $this->assertSame('2', $stats[1]->getValue(), 'две настоящие учётки — персонажи демо не в счёт');
        $this->assertSame(1, Organization::query()->real()->count());
        $this->assertTrue(Demo::isDemoOrganizationId($this->demo->id));
        $this->assertSame(3, User::where('is_demo', true)->count());
    }
}
