<?php

namespace Tests\Feature;

use App\Filament\Resources\ActivityResource;
use App\Filament\Resources\ActivityResource\Pages\ListActivities;
use App\Filament\Resources\EmployeeResource;
use App\Filament\Resources\ProjectResource;
use App\Filament\Widgets\RecordHistory;
use App\Models\Expense;
use App\Models\ProjectBudget;
use App\Models\User;
use App\Support\History;
use App\Support\Money;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Spatie\Activitylog\Models\Activity;
use Tests\Feature\Concerns\SeedsAccounting;
use Tests\TestCase;

class HistoryTest extends TestCase
{
    use RefreshDatabase, SeedsAccounting;

    private function expense(): Expense
    {
        return Expense::create(['date' => '2026-09-01', 'project_id' => $this->project->id, 'expense_category_id' => $this->cat('Материалы')->id,
            'amount' => 1000, 'payment_source' => 'bank', 'supplier' => 'Ганимед']);
    }

    public function test_changes_are_logged_with_causer_and_readable_diff(): void
    {
        $this->actingAs($this->foreman);
        $e = $this->expense();

        $this->actingAs($this->admin);
        $e->update(['amount' => 1250.5, 'supplier' => 'Ганимед СБ']);
        $e->update(['description' => null]); // без изменений — записи нет

        $log = Activity::where('subject_type', Expense::class)->orderBy('id')->get();
        $this->assertSame(['created', 'updated'], $log->pluck('event')->all());
        $this->assertSame($this->foreman->id, $log[0]->causer_id);
        $this->assertSame($this->admin->id, $log[1]->causer_id);

        $diff = History::changes($log[1]);
        $this->assertContains('Сумма: '.Money::fmt(1000).' → '.Money::fmt(1250.5), $diff);
        $this->assertContains('Поставщик: Ганимед → Ганимед СБ', $diff);
        $this->assertCount(2, $diff);
        $this->assertContains('Объект: Школа №5, АПС', History::changes($log[0]));
        $this->assertContains('Чем оплачено: Расчётный счёт', History::changes($log[0]));
    }

    public function test_trash_restore_and_force_delete_are_logged(): void
    {
        $this->actingAs($this->admin);
        $e = $this->expense();
        $e->delete();
        $e->restore();
        $e->forceDelete();

        $this->assertSame(['created', 'trashed', 'restored', 'forceDeleted'],
            Activity::where('subject_type', Expense::class)->orderBy('id')->pluck('event')->all());
    }

    public function test_user_password_is_never_logged(): void
    {
        $this->actingAs($this->admin);
        $u = User::create(['name' => 'Тест', 'email' => 't@test.ru', 'password' => 'secret123', 'role' => 'manager']);
        $u->update(['password' => 'other-secret', 'name' => 'Тест 2']);

        foreach (Activity::where('subject_type', User::class)->get() as $a) {
            $this->assertArrayNotHasKey('password', $a->properties['attributes'] ?? []);
            $this->assertArrayNotHasKey('password', $a->properties['old'] ?? []);
            $this->assertStringNotContainsString('secret', json_encode($a->properties));
        }
        $this->assertSame(['Название: Тест → Тест 2'], History::changes(Activity::where('subject_type', User::class)->latest('id')->first()));
    }

    public function test_history_page_is_admin_only(): void
    {
        $this->actingAs($this->admin);
        $this->expense()->update(['amount' => 2000]);

        $this->get(ActivityResource::getUrl('index'))->assertOk()->assertSee('История изменений');
        Livewire::test(ListActivities::class)
            ->assertSee('Изменено')
            ->assertSee(Money::fmt(2000))
            ->assertSee('Админ');
    }

    public function test_history_page_forbidden_for_manager(): void
    {
        $manager = User::create(['name' => 'М', 'email' => 'm@test.ru', 'password' => 'secret123', 'role' => 'manager']);
        $this->actingAs($manager)->get(ActivityResource::getUrl('index'))->assertForbidden();
    }

    public function test_history_page_forbidden_for_foreman(): void
    {
        $this->actingAs($this->foreman)->get(ActivityResource::getUrl('index'))->assertForbidden();
    }

    public function test_project_and_employee_pages_show_their_history(): void
    {
        $this->actingAs($this->admin);
        $this->project->update(['contract_amount' => 1200000]);
        ProjectBudget::create(['project_id' => $this->project->id, 'expense_category_id' => $this->cat('Материалы')->id, 'amount' => 50000]);
        $this->emp->update(['day_rate' => 3500]);

        $this->get(ProjectResource::getUrl('view', ['record' => $this->project]))->assertOk();
        Livewire::test(RecordHistory::class, ['record' => $this->project])
            ->assertSee('Сумма договора: '.Money::fmt(1000000).' → '.Money::fmt(1200000))
            ->assertSee('План по статье «Материалы и оборудование»')
            ->assertDontSee('Ставка в день');

        $this->get(EmployeeResource::getUrl('view', ['record' => $this->emp]))->assertOk();
        Livewire::test(RecordHistory::class, ['record' => $this->emp])
            ->assertSee('Ставка в день: '.Money::fmt(3000).' → '.Money::fmt(3500))
            ->assertDontSee('Сумма договора');

        $this->actingAs($this->foreman);
        $this->assertFalse(RecordHistory::canView());
    }
}
