<?php

namespace Tests\Feature;

use App\Filament\Resources\ExpenseResource;
use App\Filament\Resources\ExpenseResource\Pages\ListExpenses;
use App\Filament\Resources\IncomeResource\Pages\ListIncomes;
use App\Filament\Resources\WorkLogResource\Pages\ListWorkLogs;
use App\Filament\Widgets\ActiveProjects;
use App\Models\Advance;
use App\Models\Expense;
use App\Models\Income;
use App\Models\Payout;
use App\Models\WorkLog;
use App\Support\Attention;
use App\Support\Money;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Feature\Concerns\SeedsAccounting;
use Tests\TestCase;

class TrashTest extends TestCase
{
    use RefreshDatabase, SeedsAccounting;

    public function test_deleted_records_leave_totals_and_come_back_on_restore(): void
    {
        $this->fillData();
        $p = $this->project->fresh();
        $this->assertEquals(135500, $p->costTotal());

        $gsm = Expense::where('amount', 5000)->first();   // ГСМ из подотчёта
        $work = WorkLog::where('amount', 3500)->first();
        $gsm->delete();
        $work->delete();
        Income::first()->delete();
        Payout::first()->delete();
        Advance::where('amount', 2000)->first()->delete(); // возврат подотчёта

        $p = $p->fresh();
        $e = $this->emp->fresh();
        $this->assertEquals(127000, $p->costTotal());               // −5000 −3500
        $this->assertEquals(0, $p->receivedTotal());
        $this->assertEquals(6000, $e->salaryDebt());                // 6000 начислено, выплат нет
        $this->assertEquals(10000, $e->advanceBalance());           // 10000 выдано, возврат и чек удалены
        $this->assertArrayNotHasKey('Транспорт и ГСМ', $p->planFact()->keyBy('name')->all());
        $this->assertStringContainsString('Не отчитался за '.Money::fmt(10000), Attention::items()->pluck('text')->implode(' | '));

        // списки (суммы в withSum) тоже не видят удалённое
        $this->actingAs($this->admin);
        Livewire::test(ActiveProjects::class)->assertSee(Money::fmt(127000));

        foreach ([$gsm, $work, Income::onlyTrashed()->first(), Payout::onlyTrashed()->first(), Advance::onlyTrashed()->first()] as $r) {
            $r->restore();
        }

        $p = $p->fresh();
        $e = $this->emp->fresh();
        $this->assertEquals(135500, $p->costTotal());
        $this->assertEquals(300000, $p->receivedTotal());
        $this->assertEquals(5500, $e->salaryDebt());
        $this->assertEquals(3000, $e->advanceBalance());
    }

    public function test_office_restores_from_trash_in_table(): void
    {
        $this->fillData();
        $this->actingAs($this->admin);
        $exp = Expense::where('amount', 120000)->first();

        Livewire::test(ListExpenses::class)
            ->callAction(TestAction::make(DeleteAction::class)->table($exp))
            ->assertCanNotSeeTableRecords([$exp])
            ->filterTable('trashed', false)                       // «только удалённые»
            ->assertCanSeeTableRecords([$exp])
            ->callAction(TestAction::make(RestoreAction::class)->table($exp));

        $this->assertFalse($exp->fresh()->trashed());

        $inc = Income::first();
        $inc->delete();
        Livewire::test(ListIncomes::class)
            ->filterTable('trashed', true)                        // «вместе с удалёнными»
            ->assertCanSeeTableRecords([$inc])
            ->callAction(TestAction::make(RestoreAction::class)->table($inc));
        $this->assertFalse($inc->fresh()->trashed());
    }

    public function test_force_delete_removes_receipt_files(): void
    {
        Storage::fake('local');
        $path = UploadedFile::fake()->image('check.jpg')->store('receipts', 'local');
        $exp = Expense::create(['date' => '2026-09-01', 'project_id' => $this->project->id, 'expense_category_id' => $this->cat('Материалы')->id,
            'amount' => 100, 'payment_source' => 'bank', 'attachments' => [$path]]);

        $exp->delete();
        Storage::disk('local')->assertExists($path);              // в корзине файл остаётся

        $this->actingAs($this->admin);
        Livewire::test(ListExpenses::class)
            ->filterTable('trashed', false)
            ->callAction(TestAction::make(ForceDeleteAction::class)->table($exp));

        $this->assertNull(Expense::withTrashed()->find($exp->id));
        Storage::disk('local')->assertMissing($path);
    }

    public function test_foreman_has_no_trash(): void
    {
        $this->fillData();
        $this->actingAs($this->foreman); // created_by проставляется от текущего пользователя
        $own = Expense::create(['date' => '2026-09-01', 'project_id' => $this->project->id, 'expense_category_id' => $this->cat('Материалы')->id,
            'amount' => 50, 'payment_source' => 'cash']);
        $gone = Expense::create(['date' => '2026-09-01', 'project_id' => $this->project->id, 'expense_category_id' => $this->cat('Материалы')->id,
            'amount' => 60, 'payment_source' => 'cash']);
        $gone->delete();

        $this->assertFalse($this->foreman->can('delete', $own));
        $this->assertFalse($this->foreman->can('restore', $gone));
        $this->assertFalse($this->foreman->can('forceDelete', $gone));
        $this->assertTrue($this->admin->can('restore', $gone));

        Livewire::test(ListExpenses::class)
            ->assertCanSeeTableRecords([$own])
            ->assertCanNotSeeTableRecords([$gone])
            ->assertTableActionHidden(DeleteAction::class, $own);
        $this->get(ExpenseResource::getUrl('edit', ['record' => $gone]))->assertNotFound();

        Livewire::test(ListWorkLogs::class)->assertOk();
    }
}
