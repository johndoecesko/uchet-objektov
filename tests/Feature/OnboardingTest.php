<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Pages\About;
use App\Filament\Resources\EmployeeResource\Pages\ListEmployees;
use App\Filament\Resources\ExpenseResource\Pages\ListExpenses;
use App\Filament\Resources\IncomeResource\Pages\ListIncomes;
use App\Filament\Resources\ProjectResource\Pages\ListProjects;
use App\Filament\Widgets\GettingStarted;
use App\Models\Activity;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\Project;
use App\Models\ProjectBudget;
use App\Models\User;
use App\Support\Demo\ExampleData;
use App\Support\Onboarding;
use Filament\Actions\CreateAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Concerns\SeedsAccounting;
use Tests\Feature\Concerns\UsesDemo;
use Tests\TestCase;

/** Чек-лист «Первые шаги» и пустые состояния таблиц */
class OnboardingTest extends TestCase
{
    use RefreshDatabase, UsesDemo;
    use SeedsAccounting { setUp as seedAccounting; }

    private Organization $fresh;

    private User $owner;

    protected function setUp(): void
    {
        $this->seedAccounting();
        // новая пустая организация с администратором — как после регистрации
        $this->fresh = Organization::create(['name' => 'ООО «Новички»', 'slug' => 'novichki']);
        $this->owner = $this->member('Владелец', 'owner@test.ru', UserRole::Admin, $this->fresh);
        $this->useOrganization($this->fresh);
    }

    private function done(User $user): array
    {
        return collect(Onboarding::steps($user, $this->fresh))->pluck('done', 'key')->all();
    }

    public function test_checklist_reflects_real_state(): void
    {
        $this->actingAs($this->owner);
        $this->assertSame(['project' => false, 'budget' => false, 'employee' => false, 'expense' => false, 'income' => false, 'invite' => false], $this->done($this->owner));

        $p = Project::create(['name' => 'Свой объект', 'contract_amount' => 100000, 'status' => 'active']);
        ProjectBudget::create(['project_id' => $p->id, 'expense_category_id' => $this->cat('Материалы')->id, 'amount' => 50000]);
        $e = Employee::create(['name' => 'Петров', 'day_rate' => 3000]);
        Expense::create(['date' => now(), 'project_id' => $p->id, 'expense_category_id' => $this->cat('Материалы')->id, 'amount' => 100, 'payment_source' => 'bank']);
        Income::create(['date' => now(), 'project_id' => $p->id, 'amount' => 1000, 'type' => 'advance', 'method' => 'bank']);
        Invitation::create(['role' => UserRole::Foreman]);

        $this->assertSame(['project' => true, 'budget' => true, 'employee' => true, 'expense' => true, 'income' => true, 'invite' => true], $this->done($this->owner));
        $this->assertTrue(Onboarding::hidden($this->owner, $this->fresh), 'всё сделано — блок не виден');
        $this->assertFalse(GettingStarted::canView());
        $this->assertNotNull($e);
    }

    public function test_example_does_not_count(): void
    {
        $this->actingAs($this->owner);
        ExampleData::add($this->fresh);
        $this->assertSame(['project' => false, 'budget' => false, 'employee' => false, 'expense' => false, 'income' => false, 'invite' => false], $this->done($this->owner));
        $this->get('/app/novichki')->assertOk()->assertSee('Первые шаги')->assertSee('Удалить пример');
    }

    public function test_foreman_sees_short_list_and_manager_has_no_invite_step(): void
    {
        $foreman = $this->member('Прораб Н.', 'fn@test.ru', UserRole::Foreman, $this->fresh);
        $manager = $this->member('Менеджер Н.', 'mn@test.ru', UserRole::Manager, $this->fresh);
        config(['services.max.token' => 'bot-token']);

        $this->assertSame(['expense', 'max'], array_keys($this->done($foreman)));
        $this->assertSame(['project', 'budget', 'employee', 'expense', 'income', 'max'], array_keys($this->done($manager)));
        $this->assertContains('invite', array_keys($this->done($this->owner)));

        // прорабу — свой расход; офису — любой расход по настоящему объекту
        $this->actingAs($foreman);
        $p = Project::create(['name' => 'Объект', 'contract_amount' => 1, 'status' => 'active']);
        Expense::create(['date' => now(), 'project_id' => $p->id, 'expense_category_id' => $this->cat('Материалы')->id, 'amount' => 10, 'payment_source' => 'cash']);
        $this->assertTrue($this->done($foreman)['expense']);
        $this->assertTrue($this->done($manager)['expense']);
        $this->assertFalse($this->done($this->member('Прораб 2', 'f2@test.ru', UserRole::Foreman, $this->fresh))['expense']);
    }

    public function test_hide_is_per_member(): void
    {
        $manager = $this->member('Менеджер Н.', 'mn@test.ru', UserRole::Manager, $this->fresh);
        $this->actingAs($this->owner);
        Livewire::test(GettingStarted::class)->assertSee('Первые шаги')->callAction('hide');

        $this->assertTrue(Onboarding::hidden($this->owner->forgetMemberships(), $this->fresh));
        $this->assertFalse(Onboarding::hidden($manager, $this->fresh), 'у другого участника блок остаётся');
        $this->assertSame(0, Activity::acrossOrganizations()->where('organization_id', $this->fresh->id)->where('event', 'updated')->count(), 'скрытие — без истории');
        $this->get('/app/novichki')->assertDontSee('Первые шаги — сделано');

        // вернуть — из «О программе»
        Livewire::test(About::class)->assertActionVisible('showChecklist')->callAction('showChecklist');
        $this->assertFalse(Onboarding::hidden($this->owner->forgetMemberships(), $this->fresh));
    }

    public function test_no_checklist_in_demo(): void
    {
        $this->makeDemo();
        $this->enterDemo();
        $this->assertFalse(GettingStarted::canView());
        $this->get('/app/demo')->assertOk()->assertDontSee('Первые шаги')->assertDontSee('Заполнить примером');
    }

    public function test_empty_states_offer_actions_by_role(): void
    {
        $this->actingAs($this->owner);
        Livewire::test(ListProjects::class)->assertSee('Пока нет объектов')
            ->assertActionVisible(TestAction::make('fillExample')->table())
            ->assertActionVisible(TestAction::make(CreateAction::class)->table());
        Livewire::test(ListExpenses::class)->assertSee('Пока нет расходов')->assertSee('боту MAX');
        Livewire::test(ListIncomes::class)->assertSee('Пока нет поступлений');
        Livewire::test(ListEmployees::class)->assertSee('Пока нет сотрудников');

        // поиск ничего не нашёл — другой текст
        Project::create(['name' => 'Школа', 'contract_amount' => 1, 'status' => 'active']);
        Livewire::test(ListProjects::class)->searchTable('Больница')->assertSee('Ничего не найдено')->assertDontSee('Пока нет объектов');

        // прораб: расход внести может, объект и пример — нет
        $foreman = $this->member('Прораб Н.', 'fn@test.ru', UserRole::Foreman, $this->fresh);
        $this->actingAs($foreman);
        Livewire::test(ListExpenses::class)->assertSee('Пока нет расходов')
            ->assertActionVisible(TestAction::make(CreateAction::class)->table());
        $this->assertFalse($foreman->can('create', Project::class));
    }
}
