<?php

namespace Tests\Feature;

use App\Filament\Pages\Settings;
use App\Filament\Resources\ExpenseResource;
use App\Filament\Resources\IncomeResource;
use App\Filament\Resources\IncomeResource\Pages\ListIncomes;
use App\Filament\Resources\PayoutResource;
use App\Filament\Resources\PayoutResource\Pages\ListPayouts;
use App\Filament\Support\Fields;
use App\Models\Income;
use App\Models\Payout;
use App\Models\Setting;
use App\Support\Attention;
use App\Support\Money;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\Testing\TestAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Feature\Concerns\SeedsAccounting;
use Tests\TestCase;

class SettingsAndAttachmentsTest extends TestCase
{
    use RefreshDatabase, SeedsAccounting;

    private function texts(): string
    {
        return Attention::items()->pluck('text')->implode(' | ');
    }

    public function test_attention_thresholds_come_from_settings(): void
    {
        $this->fillData();
        $this->travelTo('2026-09-10'); // подотчёт выдан 01.09 — 9 дней назад

        $this->assertSame(14, Attention::advanceDays());
        $this->assertStringNotContainsString('Не отчитался', $this->texts());

        Setting::put('attention.advance_days', 7);
        $this->assertStringContainsString('Не отчитался за '.Money::fmt(3000), $this->texts());

        // прогноз 495 000 из 1 000 000 = 49,5 %: предупреждение только при пороге выше
        $this->assertStringNotContainsString('Прогноз рентабельности', $this->texts());
        Setting::put('attention.low_margin', 55);
        $this->assertStringContainsString('Прогноз рентабельности 49,5% (ниже 55%)', $this->texts());
    }

    public function test_admin_saves_settings_page(): void
    {
        $this->actingAs($this->admin);
        $this->get(Settings::getUrl())->assertOk()->assertSee('Подотчёт без отчёта');

        Livewire::test(Settings::class)
            ->assertSet('data.advance_days', 14)
            ->set('data.advance_days', 21)
            ->set('data.low_margin', 12.5)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(21, Attention::advanceDays());
        $this->assertSame(12.5, Attention::lowMargin());

        Livewire::test(Settings::class)->set('data.advance_days', 0)->call('save')->assertHasErrors(['data.advance_days']);
    }

    public function test_settings_page_forbidden_for_manager(): void
    {
        $manager = \App\Models\User::create(['name' => 'М', 'email' => 'm@test.ru', 'password' => 'secret123', 'role' => 'manager']);
        $this->actingAs($manager)->get(Settings::getUrl())->assertForbidden();
    }

    public function test_photos_are_resized_in_browser_and_pdf_allowed(): void
    {
        $f = Fields::attachments();
        $this->assertSame('1920', $f->getAutomaticallyResizeImagesWidth());
        $this->assertSame('1920', $f->getAutomaticallyResizeImagesHeight());
        $this->assertSame('contain', $f->getAutomaticallyResizeImagesMode());
        $this->assertContains('application/pdf', $f->getAcceptedFileTypes());
    }

    public function test_incomes_and_payouts_have_attachments_and_files_go_on_force_delete(): void
    {
        Storage::fake('local');
        $this->actingAs($this->admin);
        $doc = UploadedFile::fake()->create('pp.pdf', 20, 'application/pdf')->store('documents/incomes', 'local');
        $chk = UploadedFile::fake()->image('perevod.jpg')->store('documents/payouts', 'local');

        $inc = Income::create(['date' => '2026-09-01', 'project_id' => $this->project->id, 'amount' => 100, 'type' => 'advance', 'method' => 'bank', 'attachments' => [$doc]]);
        $pay = Payout::create(['date' => '2026-09-01', 'employee_id' => $this->emp->id, 'amount' => 50, 'method' => 'card', 'attachments' => [$chk]]);
        $this->assertSame([$doc], $inc->fresh()->attachments);

        $this->get(IncomeResource::getUrl('edit', ['record' => $inc]))->assertOk()->assertSee('Платёжки, акты');
        $this->get(PayoutResource::getUrl('edit', ['record' => $pay]))->assertOk()->assertSee('Чек перевода');
        $this->get(ExpenseResource::getUrl('create'))->assertOk()->assertSee('Фото чеков и документов');

        $inc->delete();
        $pay->delete();
        Livewire::test(ListIncomes::class)->filterTable('trashed', false)->callAction(TestAction::make(ForceDeleteAction::class)->table($inc));
        Livewire::test(ListPayouts::class)->filterTable('trashed', false)->callAction(TestAction::make(ForceDeleteAction::class)->table($pay));

        Storage::disk('local')->assertMissing($doc);
        Storage::disk('local')->assertMissing($chk);
    }
}
