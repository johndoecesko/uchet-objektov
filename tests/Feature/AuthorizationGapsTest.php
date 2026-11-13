<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Pages\Settings;
use App\Filament\Pages\Tenancy\EditOrganizationProfile;
use App\Filament\Resources\ExpenseCategoryResource;
use App\Filament\Resources\ExpenseCategoryResource\Pages\ListExpenseCategories;
use App\Filament\Resources\MemberResource\Pages\ListMembers;
use App\Filament\Resources\MemberResource\Widgets\PendingInvitations;
use App\Filament\Resources\ProjectResource\Pages\ViewProject;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Income;
use App\Models\Invitation;
use App\Models\Organization;
use App\Models\Setting;
use App\Models\WorkLog;
use Filament\Actions\DeleteAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Livewire\Livewire;
use Tests\Feature\Concerns\SeedsAccounting;
use Tests\TestCase;

/**
 * Действия, которые раньше проверяли роль (isAdmin/isOffice) или не проверяли ничего, — теперь идут через политики.
 * Проверка: запрет политики (здесь — Gate::before в тесте) действительно закрывает действие, а не только прячет кнопку.
 */
class AuthorizationGapsTest extends TestCase
{
    use RefreshDatabase, SeedsAccounting;

    /** Запретить способность $ability для моделей $class всем (как сделает демо-режим) */
    private function deny(string $ability, string $class): void
    {
        Gate::before(function ($user, string $a, array $args = []) use ($ability, $class) {
            $subject = $args[0] ?? null;
            $matches = $subject instanceof $class || $subject === $class;

            return $a === $ability && $matches ? false : null;
        });
    }

    // 0.9 — быстрые кнопки в карточке объекта проверяются по своим моделям, а не по Project::create

    public function test_foreman_sees_expense_and_work_buttons_on_project_page(): void
    {
        $this->actingAs($this->foreman);
        Livewire::test(ViewProject::class, ['record' => $this->project->getKey()])
            ->assertActionVisible('addExpense')
            ->assertActionVisible('addWork')
            ->assertActionHidden('addIncome')
            ->callAction('addExpense', data: [
                'date' => '2026-09-10',
                'expense_category_id' => $this->cat('Материалы')->id,
                'amount' => 555,
                'payment_source' => 'cash',
            ])
            ->assertHasNoActionErrors();

        $this->assertSame($this->foreman->id, Expense::where('amount', 555)->value('created_by'));
    }

    public function test_quick_add_follows_policy_of_its_model(): void
    {
        $this->deny('create', Expense::class);
        $this->actingAs($this->admin);
        Livewire::test(ViewProject::class, ['record' => $this->project->getKey()])
            ->assertActionHidden('addExpense')
            ->assertActionVisible('addWork')
            ->assertActionVisible('addIncome');

        $this->deny('create', Income::class);
        Livewire::test(ViewProject::class, ['record' => $this->project->getKey()])->assertActionHidden('addIncome');
        $this->assertSame(0, Expense::count() + Income::count());
        $this->assertTrue($this->admin->can('create', WorkLog::class));
    }

    // 0.4 — перестановка статей: у политики есть reorder

    public function test_reorder_of_categories_is_checked_by_policy(): void
    {
        $this->actingAs($this->admin);
        $this->assertTrue(ExpenseCategoryResource::canReorder());

        $this->actingAs($this->foreman);
        $this->assertFalse(ExpenseCategoryResource::canReorder());

        $manager = $this->member('Менеджер', 'm@test.ru', UserRole::Manager);
        $this->actingAs($manager);
        $this->assertFalse(ExpenseCategoryResource::canReorder(), 'статьи — только администратору');
    }

    public function test_reorder_denied_by_policy_does_not_touch_sort(): void
    {
        $this->deny('reorder', ExpenseCategory::class);
        $this->actingAs($this->admin);
        $before = ExpenseCategory::orderBy('id')->pluck('sort', 'id')->all();
        $ids = array_reverse(array_keys($before));

        Livewire::test(ListExpenseCategories::class)->call('reorderTable', array_map('strval', $ids));
        $this->assertSame($before, ExpenseCategory::orderBy('id')->pluck('sort', 'id')->all());
    }

    // 0.2 — приглашения в виджете: «Ссылка» и «Отозвать» проверяются политикой

    public function test_pending_invitation_actions_follow_policy(): void
    {
        $this->actingAs($this->admin);
        $invite = Invitation::create(['role' => UserRole::Foreman]);

        $this->deny('update', Invitation::class);
        $this->deny('delete', Invitation::class);
        Livewire::test(PendingInvitations::class)
            ->assertActionHidden(TestAction::make('link')->table($invite))
            ->assertActionHidden(TestAction::make(DeleteAction::class)->table($invite));
        $this->assertNotNull($invite->fresh());
    }

    public function test_admin_can_still_show_link_and_revoke_invitation(): void
    {
        $this->actingAs($this->admin);
        $invite = Invitation::create(['role' => UserRole::Foreman]);

        Livewire::test(PendingInvitations::class)
            ->assertActionVisible(TestAction::make('link')->table($invite))
            ->callAction(TestAction::make(DeleteAction::class)->table($invite));
        $this->assertNull($invite->fresh());
    }

    // 0.3 — действия на ролевых проверках переведены на политики

    public function test_invite_buttons_follow_invitation_policy(): void
    {
        $this->actingAs($this->admin);
        $this->deny('create', Invitation::class);
        Livewire::test(ListMembers::class)
            ->assertActionHidden('inviteLink')
            ->assertActionHidden('inviteEmail');
        $this->assertSame(0, Invitation::count());
    }

    public function test_organization_profile_follows_update_policy(): void
    {
        $this->deny('update', Organization::class);
        $this->actingAs($this->admin)->get('/app/montazh/profile')->assertNotFound();
        $this->assertFalse(EditOrganizationProfile::canView($this->org));
    }

    public function test_settings_save_follows_update_policy(): void
    {
        $this->actingAs($this->admin);
        $this->deny('update', Organization::class);

        $this->get('/app/montazh/settings')->assertOk();
        Livewire::test(Settings::class)
            ->assertFormFieldIsDisabled('advance_days')
            ->assertDontSee('Сохранить');
        Livewire::test(Settings::class)->set('data.advance_days', 30)->call('save')->assertForbidden();
        $this->assertSame(0, Setting::count());
    }

    public function test_admin_still_saves_settings(): void
    {
        $this->actingAs($this->admin);
        Livewire::test(Settings::class)->set('data.advance_days', 30)->set('data.low_margin', 12)->call('save')->assertHasNoErrors();
        $this->assertSame(30, (int) Setting::get('attention.advance_days'));
    }
}
