<?php

namespace App\Support\Max;

use App\Enums\ExpenseSource;
use App\Enums\PaymentSource;
use App\Enums\ProjectStatus;
use App\Filament\Resources\ExpenseResource;
use App\Models\Expense;
use App\Models\ExpenseCategory;
use App\Models\Project;
use App\Models\User;
use App\Support\Money;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Диалог бота: фото/PDF чека → объект → сумма → статья → расход.
 * Состояние — в кэше по max_user_id (30 минут), «отмена» сбрасывает.
 * Бот действует от имени привязанного пользователя и проверяет его права (политики).
 */
class MaxBot
{
    public const CODE_TTL = 600;

    public const DIALOG_TTL = 1800;

    private const PROJECT_BUTTONS = 8;

    private const MAX_FILE = 10 * 1024 * 1024;

    public function __construct(private MaxApi $api) {}

    /** Одноразовый код привязки (6 символов, 10 минут) */
    public static function linkCode(User $user): string
    {
        do {
            $code = Str::upper(Str::random(6));
            $code = strtr($code, ['0' => 'X', 'O' => 'Y', '1' => 'Z', 'I' => 'W', 'L' => 'V']);
        } while (Cache::has('max:link:'.$code));

        Cache::put('max:link:'.$code, $user->id, self::CODE_TTL);

        return $code;
    }

    public function handle(array $update): void
    {
        $type = $update['update_type'] ?? null;
        $callback = $type === 'message_callback' ? ($update['callback'] ?? []) : null;
        $message = $update['message'] ?? [];

        // только личные диалоги с ботом
        if ($type === 'message_created' && ($message['recipient']['chat_type'] ?? 'dialog') !== 'dialog') {
            return;
        }

        $maxId = (int) match ($type) {
            'message_created' => $message['sender']['user_id'] ?? 0,
            'message_callback' => $callback['user']['user_id'] ?? 0,
            'bot_started' => $update['user']['user_id'] ?? 0,
            default => 0,
        };
        if (! $maxId) {
            return;
        }

        if ($callback) {
            $this->api->answer((string) ($callback['callback_id'] ?? ''));
        }

        $text = trim((string) ($message['body']['text'] ?? ''));
        $user = User::where('max_user_id', $maxId)->first();

        if (! $user || ! $user->is_active) {
            $this->unlinked($maxId, $text, $type);

            return;
        }

        Auth::setUser($user); // created_by и автор в истории изменений — этот пользователь

        if (in_array(mb_strtolower($text), ['отмена', '/cancel', 'cancel', 'стоп'], true)) {
            $this->forget($maxId);
            $this->api->send($maxId, 'Отменил. Пришлите фото чека, когда будет нужно.');

            return;
        }

        if (! $user->can('create', Expense::class)) {
            $this->api->send($maxId, 'У вашей учётной записи нет права вносить расходы.');

            return;
        }

        $files = $type === 'message_created' ? $this->files($message['body']['attachments'] ?? []) : [];
        $state = Cache::get($this->key($maxId));

        if ($files) {
            if ($state && ($state['step'] ?? null) === 'project') {
                $state['files'] = [...$state['files'], ...$files];
                $this->save($maxId, $state);
                $this->api->send($maxId, 'Добавил файл к этому чеку ('.count($state['files']).'). На какой объект?');

                return;
            }
            $state = ['step' => 'project', 'files' => $files, 'caption' => $text];
            $this->save($maxId, $state);
            $this->askProject($maxId);

            return;
        }

        if (! $state) {
            $this->api->send($maxId, "Пришлите фото чека или PDF — спрошу объект, сумму и статью и внесу расход.\nПодпись к фото попадёт в описание. «отмена» — начать заново.");

            return;
        }

        $payload = (string) ($callback['payload'] ?? '');

        match ($state['step']) {
            'project' => $this->onProject($maxId, $state, $payload, $text),
            'amount' => $this->onAmount($maxId, $state, $text),
            'category' => $this->onCategory($maxId, $state, $payload, $user),
            default => $this->forget($maxId),
        };
    }

    private function unlinked(int $maxId, string $text, ?string $type): void
    {
        $code = Str::upper(preg_replace('/\s+/', '', $text));
        if ($type === 'message_created' && preg_match('/^[A-Z0-9]{6}$/', $code) && ($userId = Cache::pull('max:link:'.$code))) {
            $user = User::find($userId);
            if ($user && $user->is_active) {
                DB::transaction(function () use ($user, $maxId) {
                    User::where('max_user_id', $maxId)->update(['max_user_id' => null]);
                    $user->forceFill(['max_user_id' => $maxId])->save();
                });
                $this->api->send($maxId, "Готово, MAX привязан к учётной записи «{$user->name}».\nПришлите фото чека или PDF — внесу расход.");

                return;
            }
        }

        $this->api->send($maxId, 'Пришлите код из приложения «Учёт объектов»: меню пользователя (кружок справа вверху) → «Подключить MAX». Код действует 10 минут.');
    }

    /** Фото и PDF из вложений сообщения */
    private function files(array $attachments): array
    {
        $out = [];
        foreach ($attachments as $a) {
            $url = $a['payload']['url'] ?? null;
            if (! $url) {
                continue;
            }
            if (($a['type'] ?? null) === 'image') {
                $out[] = ['url' => $url, 'ext' => 'jpg'];
            } elseif (($a['type'] ?? null) === 'file') {
                $ext = Str::lower(pathinfo((string) ($a['filename'] ?? ''), PATHINFO_EXTENSION));
                if (in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'webp', 'heic'], true)) {
                    $out[] = ['url' => $url, 'ext' => $ext];
                }
            }
        }

        return $out;
    }

    private function openProjects()
    {
        return Project::query()
            ->whereIn('status', [ProjectStatus::Planned->value, ProjectStatus::Active->value])
            ->orderBy('name');
    }

    private function askProject(int $maxId, ?iterable $projects = null, string $prefix = 'На какой объект?'): void
    {
        $projects ??= $this->openProjects()->limit(self::PROJECT_BUTTONS + 1)->get();
        $list = collect($projects);
        $buttons = $list->take(self::PROJECT_BUTTONS)
            ->map(fn (Project $p) => [['text' => $p->name, 'payload' => 'p:'.$p->id]])->values()->all();
        $more = $list->count() > self::PROJECT_BUTTONS;

        $this->api->send($maxId, $prefix.($more ? "\nНет в списке — напишите часть названия." : ''), $buttons);
    }

    private function onProject(int $maxId, array $state, string $payload, string $text): void
    {
        $project = null;
        if (str_starts_with($payload, 'p:')) {
            $project = $this->openProjects()->find((int) substr($payload, 2));
        } elseif ($text !== '') {
            $found = $this->openProjects()->where('name', 'like', '%'.str_replace(['%', '_'], ['\%', '\_'], $text).'%')->limit(self::PROJECT_BUTTONS + 1)->get();
            if ($found->count() === 1) {
                $project = $found->first();
            } elseif ($found->isNotEmpty()) {
                $this->askProject($maxId, $found, 'Нашёл несколько — выберите:');

                return;
            } else {
                $this->askProject($maxId, null, 'Не нашёл объект «'.$text.'». Выберите из списка или напишите иначе:');

                return;
            }
        }

        if (! $project) {
            $this->askProject($maxId);

            return;
        }

        $state['project_id'] = $project->id;
        $state['step'] = 'amount';
        $this->save($maxId, $state);
        $this->api->send($maxId, "Объект: {$project->name}.\nСумма по чеку? Например: 1 250,50");
    }

    /** «1 250,50», «1250.5 ₽», «1 250 р» → 1250.5 */
    public static function parseAmount(string $text): ?float
    {
        $s = preg_replace('/(руб\.?|р\.?|₽)$/u', '', mb_strtolower(trim($text)));
        $s = preg_replace('/[\s\x{00A0}\x{202F}]/u', '', (string) $s);
        $s = str_replace(',', '.', (string) $s);
        if (! preg_match('/^\d+(\.\d{1,2})?$/', $s)) {
            return null;
        }
        $v = (float) $s;

        return $v > 0 && $v < 100_000_000 ? round($v, 2) : null;
    }

    private function onAmount(int $maxId, array $state, string $text): void
    {
        $amount = self::parseAmount($text);
        if ($amount === null) {
            $this->api->send($maxId, 'Не понял сумму. Напишите числом, например: 1 250,50');

            return;
        }

        $state['amount'] = $amount;
        $state['step'] = 'category';
        $this->save($maxId, $state);

        $buttons = ExpenseCategory::query()
            ->where('is_active', true)->where('is_labor', false)->where('is_tax', false)
            ->orderBy('sort')->orderBy('name')->get()
            ->map(fn (ExpenseCategory $c) => ['text' => $c->name, 'payload' => 'c:'.$c->id])
            ->chunk(2)->map(fn ($row) => $row->values()->all())->values()->all();

        $this->api->send($maxId, 'Сумма: '.Money::fmt($amount).'. Статья затрат?', $buttons);
    }

    private function onCategory(int $maxId, array $state, string $payload, User $user): void
    {
        $category = str_starts_with($payload, 'c:')
            ? ExpenseCategory::where('is_active', true)->where('is_labor', false)->where('is_tax', false)->find((int) substr($payload, 2))
            : null;
        $project = $this->openProjects()->find($state['project_id'] ?? 0);

        if (! $category || ! $project) {
            $this->api->send($maxId, $project ? 'Выберите статью кнопкой выше.' : 'Объект больше не доступен. Пришлите чек заново.');
            if (! $project) {
                $this->forget($maxId);
            }

            return;
        }

        $paths = [];
        try {
            foreach ($state['files'] as $f) {
                $path = 'receipts/'.Str::ulid().'.'.$f['ext'];
                Storage::disk('local')->put($path, $this->api->download($f['url'], self::MAX_FILE));
                $paths[] = $path;
            }
        } catch (Throwable $e) {
            Storage::disk('local')->delete($paths);
            Log::warning('MAX: не удалось скачать вложение', ['max_user_id' => $maxId, 'error' => $e->getMessage()]);
            $this->api->send($maxId, 'Не получилось скачать файл чека. Пришлите его ещё раз.');
            $this->forget($maxId);

            return;
        }

        [$supplier, $description] = $this->caption((string) ($state['caption'] ?? ''));
        $employee = $user->employee()->where('is_active', true)->first();

        $expense = new Expense([
            'date' => now()->toDateString(),
            'project_id' => $project->id,
            'expense_category_id' => $category->id,
            'amount' => $state['amount'],
            'supplier' => $supplier,
            'description' => $description,
            'payment_source' => $employee ? PaymentSource::Advance : PaymentSource::Bank,
            'employee_id' => $employee?->id,
            'attachments' => $paths,
        ]);
        $expense->source = ExpenseSource::Max;
        $expense->save();
        $this->forget($maxId);

        $this->api->send(
            $maxId,
            "Расход №{$expense->id} на объект «{$project->name}» добавлен: ".Money::fmt($expense->amount).", {$category->name}"
                .($employee ? ', из подотчёта.' : '.'),
            [[['text' => 'Открыть в учёте', 'url' => ExpenseResource::getUrl('edit', ['record' => $expense], panel: 'admin')]]],
        );
    }

    /** Подпись к фото: первая строка — поставщик (если строк несколько), остальное — описание */
    private function caption(string $caption): array
    {
        $lines = array_values(array_filter(array_map('trim', preg_split('/\R/u', $caption) ?: [])));
        if (count($lines) > 1) {
            return [mb_substr($lines[0], 0, 255), implode("\n", array_slice($lines, 1))];
        }

        return [null, $lines[0] ?? null];
    }

    private function key(int $maxId): string
    {
        return 'max:dialog:'.$maxId;
    }

    private function save(int $maxId, array $state): void
    {
        Cache::put($this->key($maxId), $state, self::DIALOG_TTL);
    }

    private function forget(int $maxId): void
    {
        Cache::forget($this->key($maxId));
    }
}
