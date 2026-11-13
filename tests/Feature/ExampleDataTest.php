<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Widgets\GettingStarted;
use App\Models\Activity;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Income;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectBudget;
use App\Models\User;
use App\Models\WorkLog;
use App\Support\Demo\ExampleData;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Feature\Concerns\SeedsAccounting;
use Tests\TestCase;

/** «Заполнить примером» / «Удалить пример»: только своя организация, без истории, удаляется без следов */
class ExampleDataTest extends TestCase
{
    use RefreshDatabase;
    use SeedsAccounting { setUp as seedAccounting; }

    private Organization $fresh;

    private User $owner;

    protected function setUp(): void
    {
        $this->seedAccounting();
        $this->fresh = Organization::create(['name' => 'ООО «Новички»', 'slug' => 'novichki']);
        $this->owner = $this->member('Владелец', 'owner@test.ru', UserRole::Admin, $this->fresh);
        $this->useOrganization($this->fresh);
    }

    /** Все строки организации по таблицам данных (с корзиной) */
    private function rows(Organization $org): array
    {
        $out = [];
        foreach (['projects', 'project_budgets', 'employees', 'expenses', 'work_logs', 'payouts', 'advances', 'incomes', 'activity_log', 'expense_categories'] as $t) {
            $out[$t] = DB::table($t)->where('organization_id', $org->id)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
        }

        return $out;
    }

    public function test_example_is_created_only_in_own_organization_without_history(): void
    {
        $this->actingAs($this->owner);
        $other = $this->rows($this->org);
        $history = Activity::acrossOrganizations()->where('organization_id', $this->fresh->id)->count(); // добавление владельца

        Livewire::test(GettingStarted::class)->assertActionVisible('fillExample')->callAction('fillExample');

        $project = Project::query()->example()->sole();
        $this->assertSame($this->fresh->id, $project->organization_id);
        $this->assertSame(ExampleData::PROJECT_NAME, $project->name);
        $this->assertSame(2, Employee::query()->example()->count());
        $this->assertGreaterThan(3, Expense::where('project_id', $project->id)->count());
        $this->assertGreaterThan(5, WorkLog::where('project_id', $project->id)->count());
        $this->assertSame(2, Income::where('project_id', $project->id)->count());
        $this->assertSame($this->owner->id, Expense::where('project_id', $project->id)->value('created_by'));
        $this->assertSame($history, Activity::acrossOrganizations()->where('organization_id', $this->fresh->id)->count(), 'пример не пишет историю');
        $this->assertEquals($other, $this->rows($this->org), 'другая организация не тронута');
        $this->assertGreaterThan(0, $project->forecastProfit());

        // второй раз не предлагается
        Livewire::test(GettingStarted::class)->assertActionHidden('fillExample')->assertActionVisible('removeExample');
    }

    public function test_not_offered_when_own_projects_exist(): void
    {
        $this->actingAs($this->owner);
        Project::create(['name' => 'Свой', 'contract_amount' => 1, 'status' => 'active']);
        Livewire::test(GettingStarted::class)->assertActionHidden('fillExample');
    }

    public function test_survives_renamed_categories(): void
    {
        $this->actingAs($this->owner);
        ExpenseCategory::where('name', 'Материалы и оборудование')->update(['name' => 'Оборудование']);
        ExpenseCategory::where('name', 'Работа (зарплата из журнала)')->update(['name' => 'ФОТ']);
        ExpenseCategory::where('name', 'Транспорт и ГСМ')->delete();

        $project = ExampleData::add($this->fresh);
        $this->assertTrue(ProjectBudget::where('project_id', $project->id)->where('expense_category_id', ExpenseCategory::where('name', 'Оборудование')->value('id'))->exists());
        $this->assertTrue(ProjectBudget::where('project_id', $project->id)->where('expense_category_id', ExpenseCategory::laborId())->exists());
        $this->assertSame(0, Expense::whereNull('expense_category_id')->count());
    }

    public function test_removed_completely_including_user_records_trash_history_and_files(): void
    {
        Storage::fake('local');
        $this->actingAs($this->owner);
        $before = $this->rows($this->fresh);
        $otherBefore = $this->rows($this->org);

        $project = ExampleData::add($this->fresh);
        $worker = Employee::query()->example()->first();
        // пользователь внёс своё в объект-пример: расход с файлом (и удалил его в корзину), поступление, правку объекта
        Storage::disk('local')->put('receipts/'.$this->fresh->id.'/check.jpg', 'x');
        $own = Expense::create(['date' => now(), 'project_id' => $project->id, 'expense_category_id' => $this->cat('Прочее')->id, 'amount' => 77,
            'payment_source' => 'cash', 'attachments' => ['receipts/'.$this->fresh->id.'/check.jpg']]);
        $own->delete();
        Income::create(['date' => now(), 'project_id' => $project->id, 'amount' => 5, 'type' => 'advance', 'method' => 'bank']);
        $project->update(['notes' => 'моя правка']);
        $this->assertGreaterThan(0, Activity::acrossOrganizations()->where('organization_id', $this->fresh->id)->count());

        Livewire::test(GettingStarted::class)->callAction('removeExample');

        $this->assertEquals($before, $this->rows($this->fresh), 'от примера ничего не осталось — ни записей, ни корзины, ни истории');
        $this->assertEquals($otherBefore, $this->rows($this->org));
        Storage::disk('local')->assertMissing('receipts/'.$this->fresh->id.'/check.jpg');
        $this->assertNull(Employee::find($worker->id));
        $this->assertFalse(ExampleData::exists());
    }

    public function test_real_data_stays_and_used_employee_is_kept_without_flag(): void
    {
        $this->actingAs($this->owner);
        $real = Project::create(['name' => 'Свой объект', 'contract_amount' => 500000, 'status' => 'active']);
        $realExpense = Expense::create(['date' => now(), 'project_id' => $real->id, 'expense_category_id' => $this->cat('Материалы')->id, 'amount' => 1000, 'payment_source' => 'bank']);
        ExampleData::add($this->fresh);
        $worker = Employee::query()->example()->orderBy('id')->first();
        WorkLog::create(['date' => now(), 'project_id' => $real->id, 'employee_id' => $worker->id, 'days' => 1, 'rate' => 3500]);

        $result = ExampleData::remove($this->fresh);

        $this->assertSame([$worker->name], $result['kept']);
        $kept = Employee::find($worker->id);
        $this->assertNotNull($kept);
        $this->assertFalse($kept->is_example);
        $this->assertSame(1, WorkLog::where('project_id', $real->id)->count(), 'работа на своём объекте осталась');
        $this->assertNotNull($real->fresh());
        $this->assertNotNull($realExpense->fresh());
        $this->assertSame(0, Project::query()->example()->count());
        $this->assertSame(0, Employee::query()->example()->count());
    }

    public function test_foreman_can_neither_fill_nor_remove(): void
    {
        $foreman = $this->member('Прораб Н.', 'fn@test.ru', UserRole::Foreman, $this->fresh);
        $this->actingAs($foreman);
        Livewire::test(GettingStarted::class)->assertActionHidden('fillExample');

        $this->actingAs($this->owner);
        ExampleData::add($this->fresh);
        $this->actingAs($foreman);
        Livewire::test(GettingStarted::class)->assertActionHidden('removeExample');
        $this->assertTrue(ExampleData::exists());
    }

    public function test_cannot_fill_other_organization(): void
    {
        $this->actingAs($this->owner);
        $this->assertThrows(fn () => ExampleData::add($this->org), \LogicException::class);
        $this->assertFalse(Project::acrossOrganizations()->where('organization_id', $this->org->id)->where('is_example', true)->exists());
    }
}
