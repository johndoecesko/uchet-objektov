<?php

namespace Tests\Feature;

use App\Enums\ExpenseSource;
use App\Enums\PaymentSource;
use App\Models\Expense;
use App\Support\Max\MaxBot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Spatie\Activitylog\Models\Activity;
use Tests\Feature\Concerns\SeedsAccounting;
use Tests\TestCase;

class MaxBotTest extends TestCase
{
    use RefreshDatabase, SeedsAccounting {
        SeedsAccounting::setUp as seedAccounting;
    }

    private const MAX_ID = 777001;

    private const SECRET = 'test-secret_123';

    protected function setUp(): void
    {
        $this->seedAccounting();
        config(['services.max.token' => 'bot-token', 'services.max.webhook_secret' => self::SECRET, 'services.max.url' => 'https://platform-api2.max.ru']);
        Storage::fake('local');
        Http::fake([
            'platform-api2.max.ru/*' => Http::response(['success' => true]),
            'files.max.test/*' => Http::response('JPEG-DATA', 200, ['Content-Type' => 'image/jpeg']),
        ]);
    }

    private function hook(array $update, ?string $secret = self::SECRET)
    {
        return $this->postJson('/api/max/webhook', $update, $secret ? ['X-Max-Bot-Api-Secret' => $secret] : []);
    }

    private function text(string $text, array $attachments = []): array
    {
        return ['update_type' => 'message_created', 'timestamp' => 1, 'message' => [
            'sender' => ['user_id' => self::MAX_ID, 'first_name' => 'Иван', 'is_bot' => false],
            'recipient' => ['chat_type' => 'dialog', 'chat_id' => 5, 'user_id' => 1],
            'timestamp' => 1,
            'body' => ['mid' => 'mid.'.uniqid(), 'seq' => 1, 'text' => $text, 'attachments' => $attachments],
        ]];
    }

    private function press(string $payload): array
    {
        return ['update_type' => 'message_callback', 'timestamp' => 1, 'callback' => [
            'timestamp' => 1, 'callback_id' => 'cb.'.uniqid(), 'payload' => $payload,
            'user' => ['user_id' => self::MAX_ID, 'first_name' => 'Иван', 'is_bot' => false],
        ], 'message' => null];
    }

    /** Последнее сообщение, отправленное ботом пользователю */
    private function lastReply(): array
    {
        $sent = collect(Http::recorded())->map(fn ($p) => $p[0])
            ->filter(fn (Request $r) => str_contains($r->url(), '/messages?user_id='.self::MAX_ID));
        $this->assertNotEmpty($sent, 'бот ничего не ответил');

        return $sent->last()->data();
    }

    private function buttons(array $reply): array
    {
        return collect($reply['attachments'][0]['payload']['buttons'] ?? [])->flatten(1)->all();
    }

    public function test_wrong_or_missing_secret_is_forbidden(): void
    {
        $this->hook($this->text('привет'), 'wrong-secret')->assertForbidden();
        $this->hook($this->text('привет'), null)->assertForbidden();
        Http::assertNothingSent();
    }

    public function test_unknown_user_gets_hint_about_code(): void
    {
        $this->hook($this->text('привет'))->assertOk();
        $this->assertStringContainsString('Подключить MAX', $this->lastReply()['text']);
        Http::assertSent(fn (Request $r) => $r->hasHeader('Authorization', 'bot-token'));
    }

    public function test_link_by_one_time_code(): void
    {
        $code = MaxBot::linkCode($this->foreman);
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{6}$/', $code);

        $this->hook($this->text('abc'))->assertOk();                       // неверный код
        $this->assertNull($this->foreman->fresh()->max_user_id);

        $this->hook($this->text(strtolower($code)))->assertOk();
        $this->assertSame(self::MAX_ID, $this->foreman->fresh()->max_user_id);
        $this->assertStringContainsString('привязан', $this->lastReply()['text']);

        // код одноразовый
        $this->admin->forceFill(['max_user_id' => null])->save();
        $this->assertNull(cache('max:link:'.$code));
    }

    public function test_full_dialog_creates_expense_as_linked_user(): void
    {
        $this->foreman->forceFill(['max_user_id' => self::MAX_ID])->save();
        $this->emp->update(['user_id' => $this->foreman->id]);

        $this->hook($this->text("ООО Ганимед\nКабель КПСнг 2x0,75", [
            ['type' => 'image', 'payload' => ['photo_id' => 1, 'token' => 't', 'url' => 'https://files.max.test/photo1.jpg']],
        ]))->assertOk();
        $reply = $this->lastReply();
        $this->assertStringContainsString('На какой объект', $reply['text']);
        $this->assertContains(['type' => 'callback', 'text' => 'Школа №5, АПС', 'payload' => 'p:'.$this->project->id], $this->buttons($reply));

        $this->hook($this->press('p:'.$this->project->id))->assertOk();
        Http::assertSent(fn (Request $r) => str_contains($r->url(), '/answers?callback_id=cb.'));
        $this->assertStringContainsString('Сумма по чеку', $this->lastReply()['text']);

        $this->hook($this->text('сто рублей'))->assertOk();
        $this->assertStringContainsString('Не понял сумму', $this->lastReply()['text']);

        $this->hook($this->text('1 250,50'))->assertOk();
        $reply = $this->lastReply();
        $this->assertStringContainsString('Статья затрат', $reply['text']);
        $mat = $this->cat('Материалы');
        $this->assertContains(['type' => 'callback', 'text' => $mat->name, 'payload' => 'c:'.$mat->id], $this->buttons($reply));
        $this->assertNotContains('Работа (зарплата из журнала)', array_column($this->buttons($reply), 'text'));

        $this->hook($this->press('c:'.$mat->id))->assertOk();

        $e = Expense::latest('id')->first();
        $this->assertNotNull($e);
        $this->assertEquals(1250.5, (float) $e->amount);
        $this->assertSame($this->project->id, $e->project_id);
        $this->assertSame($mat->id, $e->expense_category_id);
        $this->assertSame(PaymentSource::Advance, $e->payment_source);
        $this->assertSame($this->emp->id, $e->employee_id);
        $this->assertSame($this->foreman->id, $e->created_by);
        $this->assertSame(ExpenseSource::Max, $e->source);
        $this->assertSame('ООО Ганимед', $e->supplier);
        $this->assertSame('Кабель КПСнг 2x0,75', $e->description);
        $this->assertSame(now()->toDateString(), $e->date->toDateString());
        $this->assertCount(1, $e->attachments);
        $this->assertStringStartsWith('receipts/', $e->attachments[0]);
        Storage::disk('local')->assertExists($e->attachments[0]);
        $this->assertSame('JPEG-DATA', Storage::disk('local')->get($e->attachments[0]));

        $reply = $this->lastReply();
        $this->assertStringContainsString("Расход №{$e->id} на объект «Школа №5, АПС» добавлен", $reply['text']);
        $this->assertStringEndsWith('/expenses/'.$e->id.'/edit', $this->buttons($reply)[0]['url']);
        $this->assertSame($this->foreman->id, Activity::where('subject_type', Expense::class)->where('subject_id', $e->id)->first()->causer_id);

        // диалог закрыт: следующий текст — подсказка
        $this->hook($this->text('ещё?'))->assertOk();
        $this->assertStringContainsString('Пришлите фото чека', $this->lastReply()['text']);
    }

    public function test_without_employee_payment_is_bank_and_project_found_by_text(): void
    {
        $this->admin->forceFill(['max_user_id' => self::MAX_ID])->save();

        $this->hook($this->text('', [['type' => 'file', 'filename' => 'schet.pdf', 'size' => 10, 'payload' => ['token' => 't', 'url' => 'https://files.max.test/schet.pdf']]]))->assertOk();
        $this->hook($this->text('Школа'))->assertOk(); // в MySQL поиск и без учёта регистра
        $this->hook($this->text('500'))->assertOk();
        $this->hook($this->press('c:'.$this->cat('ГСМ')->id))->assertOk();

        $e = Expense::latest('id')->first();
        $this->assertSame(PaymentSource::Bank, $e->payment_source);
        $this->assertNull($e->employee_id);
        $this->assertStringEndsWith('.pdf', $e->attachments[0]);
        $this->assertSame($this->admin->id, $e->created_by);
    }

    public function test_cancel_resets_dialog(): void
    {
        $this->foreman->forceFill(['max_user_id' => self::MAX_ID])->save();
        $this->hook($this->text('', [['type' => 'image', 'payload' => ['url' => 'https://files.max.test/p.jpg']]]))->assertOk();
        $this->hook($this->text('Отмена'))->assertOk();
        $this->assertStringContainsString('Отменил', $this->lastReply()['text']);
        $this->hook($this->press('p:'.$this->project->id))->assertOk();
        $this->assertStringContainsString('Пришлите фото чека', $this->lastReply()['text']);
        $this->assertSame(0, Expense::count());
    }

    public function test_group_chats_are_ignored(): void
    {
        $u = $this->text('привет');
        $u['message']['recipient']['chat_type'] = 'chat';
        $this->hook($u)->assertOk();
        Http::assertNothingSent();
    }

    public function test_parse_amount(): void
    {
        $this->assertSame(1250.5, MaxBot::parseAmount('1 250,50'));
        $this->assertSame(1250.5, MaxBot::parseAmount("1\u{00A0}250.5 ₽"));
        $this->assertSame(990.0, MaxBot::parseAmount('990 р'));
        $this->assertNull(MaxBot::parseAmount('0'));
        $this->assertNull(MaxBot::parseAmount('12,345'));
        $this->assertNull(MaxBot::parseAmount('abc'));
    }

    public function test_user_menu_has_link_action(): void
    {
        $this->actingAs($this->foreman)->get('/')->assertOk()->assertSee('Подключить MAX');
    }

    public function test_user_menu_hidden_without_bot_token(): void
    {
        config(['services.max.token' => null]);
        $this->actingAs($this->foreman)->get('/')->assertOk()->assertDontSee('Подключить MAX');
    }
}
