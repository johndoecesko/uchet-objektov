<?php

namespace App\Support\Demo;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use App\Support\CurrentOrganization;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;

/**
 * Создание и ночное пересоздание публичного демо (app:demo-reset).
 *
 * Пересоздание удаляет ТОЛЬКО строки с organization_id демо-организации — по константному списку таблиц,
 * через одну функцию purge(); id демо проверяется по флагу is_demo и адресу. До и после считаются строки
 * всех остальных организаций — изменилось хоть что-то, транзакция откатывается. Организация, персонажи
 * и их членства сохраняются (строки пользователей не меняются) — сессии гостей не рвутся.
 */
class DemoReset
{
    /** Персонажи демо: [почта, имя, роль]. Домен .invalid — письма на него не уходят никогда */
    public const PERSONAS = [
        'admin' => ['demo-admin@finprorab.invalid', 'Анна Директорова', UserRole::Admin],
        'manager' => ['demo-manager@finprorab.invalid', 'Марина Счётова', UserRole::Manager],
        'foreman' => ['demo-foreman@finprorab.invalid', 'Игорь Монтажов', UserRole::Foreman],
    ];

    public const ORGANIZATION_NAME = 'ООО «Демо-Монтаж»';

    /** Таблицы данных организации — в порядке удаления (сначала ссылающиеся) */
    public const TABLES = [
        'expenses', 'work_logs', 'incomes', 'payouts', 'advances', 'project_budgets', 'projects', 'employees',
        'invitations', 'settings', 'expense_categories', 'activity_log',
    ];

    /** id демо-организации, проверенный в этой операции; purge() удаляет только его строки */
    private ?int $verifiedId = null;

    /**
     * Демо-организация: создать (--create) или найти существующую.
     * Отказ: адрес занят настоящей организацией, демо уже есть (при создании), почта персонажа занята настоящей учёткой.
     */
    public function ensureOrganization(bool $create): Organization
    {
        $existing = Organization::query()->where('is_demo', true)->get();
        if ($existing->count() > 1) {
            throw new RuntimeException('Демо-организаций больше одной: '.$existing->pluck('id')->implode(', '));
        }
        if ($existing->count() === 1) {
            if ($create) {
                throw new RuntimeException('Демо-организация уже есть (id '.$existing->first()->id.')');
            }

            return $existing->first();
        }
        if (! $create) {
            throw new RuntimeException('Демо-организации нет — сначала app:demo-reset --create');
        }

        $slug = Demo::slug();
        if (Organization::query()->where('slug', $slug)->exists()) {
            throw new RuntimeException("Адрес «{$slug}» занят настоящей организацией — демо не создано");
        }
        $taken = User::query()->whereIn('email', array_column(self::PERSONAS, 0))->pluck('email');
        if ($taken->isNotEmpty()) {
            throw new RuntimeException('Почта персонажа занята: '.$taken->implode(', '));
        }

        return Demo::allowWrites(fn () => $this->withTenant(null, fn () => DB::transaction(function () use ($slug) {
            $org = new Organization(['name' => self::ORGANIZATION_NAME]);
            $org->forceFill(['slug' => $slug, 'is_demo' => true])->save(); // created → справочник статей

            foreach (self::PERSONAS as [$email, $name, $role]) {
                $user = new User(['name' => $name, 'email' => $email, 'is_active' => true]);
                $user->forceFill([
                    'password' => Hash::make(Str::random(64)), // входа по паролю нет; строку потом не трогаем
                    'has_password' => false,
                    'is_demo' => true,
                    'email_verified_at' => now(),
                    'current_organization_id' => $org->id,
                ])->save();
                $org->addMember($user, $role);
            }

            return $org;
        })));
    }

    /**
     * Пересоздать данные демо. Возвращает число строк по таблицам после заполнения.
     *
     * @return array<string, int>
     */
    public function run(): array
    {
        $lock = Cache::lock('demo-reset', 900);
        if (! $lock->get()) {
            throw new RuntimeException('Пересоздание демо уже идёт');
        }

        try {
            return Demo::allowWrites(function () {
                $org = $this->verifiedOrganization();
                $id = $this->verifiedId;

                $result = $this->withTenant($org, fn () => app(CurrentOrganization::class)->run($org, fn () => DB::transaction(function () use ($org, $id) {
                    $before = $this->foreignCounts($id);

                    foreach (self::TABLES as $table) {
                        $this->purge($table);
                    }
                    $this->purgePersonaHistory();

                    $org->seedDefaults();
                    $personas = $this->personas($org);
                    (new DemoData($org))->fill($personas);

                    $after = $this->foreignCounts($id);
                    if ($after !== $before) {
                        throw new RuntimeException('Пересоздание демо задело другие организации — откат: '
                            .json_encode(array_diff_assoc($after, $before), JSON_UNESCAPED_UNICODE));
                    }

                    return array_combine(self::TABLES, array_map(
                        fn (string $t) => DB::table($t)->where('organization_id', $id)->count(), self::TABLES));
                })));

                $this->afterPurge($id);

                return $result;
            });
        } finally {
            $this->verifiedId = null;
            $lock->release();
        }
    }

    /** Удалить демо целиком (откат): данные, членства, персонажей, организацию. Только с --force */
    public function destroy(bool $force): void
    {
        if (! $force) {
            throw new RuntimeException('Удаление демо — только с --force');
        }

        Demo::allowWrites(function () {
            $this->verifiedOrganization();
            $id = $this->verifiedId;

            try {
                DB::transaction(function () use ($id) {
                    $before = $this->foreignCounts($id);
                    foreach (self::TABLES as $table) {
                        $this->purge($table);
                    }
                    $this->purgePersonaHistory();

                    $personaIds = $this->personaIds();
                    $others = DB::table('organization_user')->whereIn('user_id', $personaIds)->where('organization_id', '<>', $id)->count();
                    if ($others > 0) {
                        throw new RuntimeException('Персонаж демо состоит в другой организации — удаление остановлено');
                    }
                    DB::table('organization_user')->where('organization_id', $id)->delete();
                    if (config('session.driver') === 'database') {
                        DB::table(config('session.table', 'sessions'))->whereIn('user_id', $personaIds)->delete();
                    }
                    DB::table('users')->whereIn('id', $personaIds)->where('is_demo', true)->delete();
                    DB::table('organizations')->where('id', $id)->where('is_demo', true)->delete();

                    // минус удалённые строки демо: персонажи и сама организация
                    $after = $this->foreignCounts($id);
                    $before['users'] -= count($personaIds);
                    $before['organizations'] -= 1;
                    if ($after !== $before) {
                        throw new RuntimeException('Удаление демо задело другие организации — откат: '
                            .json_encode(['было' => $before, 'стало' => $after], JSON_UNESCAPED_UNICODE));
                    }
                });
                $this->afterPurge($id);
            } finally {
                $this->verifiedId = null;
            }
        });
    }

    /**
     * Тенант Filament на время записи: его хук creating переназначает organization_id новых записей
     * на текущий тенант панели — при чужом тенанте (например, в тестах) данные демо ушли бы не туда.
     */
    private function withTenant(?Organization $org, Closure $callback): mixed
    {
        $panel = Filament::getCurrentPanel();
        $tenant = Filament::getTenant();
        Filament::setCurrentPanel(Filament::getPanel('admin'));
        Filament::setTenant($org, isQuiet: true);

        try {
            return $callback();
        } finally {
            Filament::setTenant($tenant, isQuiet: true);
            Filament::setCurrentPanel($panel);
        }
    }

    /** Демо-организация с проверками: один экземпляр, флаг, адрес, только персонажи в участниках */
    private function verifiedOrganization(): Organization
    {
        $org = Demo::organizationOrFail();
        $id = (int) $org->getKey();
        if ($id <= 0 || ! $org->is_demo || ! Organization::query()->whereKey($id)->where('is_demo', true)->exists()) {
            throw new RuntimeException('Демо-организация не прошла проверку');
        }
        $this->verifiedId = $id;

        return $org;
    }

    /** Единственное место, где удаляются данные: только таблица из списка и только строки проверенной демо-организации */
    private function purge(string $table): void
    {
        if (! in_array($table, self::TABLES, true)) {
            throw new LogicException("Таблица {$table} не из списка демо");
        }
        $id = $this->verifiedId;
        if (! is_int($id) || $id <= 0 || ! Organization::query()->whereKey($id)->where('is_demo', true)->exists()) {
            throw new LogicException('Демо-организация не проверена');
        }

        DB::table($table)->where('organization_id', $id)->delete();
    }

    /** История персонажей вне организаций (создание учёток) — не копится от пересоздания к пересозданию */
    private function purgePersonaHistory(): void
    {
        $ids = $this->personaIds();
        if ($ids === []) {
            return;
        }
        DB::table('activity_log')->whereNull('organization_id')
            ->where(fn ($q) => $this->personaActivity($q, $ids))
            ->delete();
    }

    private function personaActivity($query, array $ids)
    {
        return $query
            ->where(fn ($q) => $q->where('subject_type', (new User)->getMorphClass())->whereIn('subject_id', $ids))
            ->orWhere(fn ($q) => $q->where('causer_type', (new User)->getMorphClass())->whereIn('causer_id', $ids));
    }

    /**
     * Строки всех остальных организаций по таблицам списка + участники, учётки, организации.
     * Для activity_log без организации — кроме истории персонажей (её чистит purgePersonaHistory).
     *
     * @return array<string, int>
     */
    private function foreignCounts(int $id): array
    {
        $personaIds = $this->personaIds();
        $counts = [];
        foreach (self::TABLES as $table) {
            $counts[$table] = DB::table($table)->where(fn ($w) => $w->where('organization_id', '<>', $id)->orWhereNull('organization_id'))->count();
        }
        // минус история персонажей вне организаций; считаем положительным фильтром — NOT(...) по строкам
        // с пустыми subject/causer дал бы NULL и потерял бы чужие строки
        if ($personaIds !== []) {
            $counts['activity_log'] -= DB::table('activity_log')->whereNull('organization_id')
                ->where(fn ($q) => $this->personaActivity($q, $personaIds))->count();
        }
        $counts['organization_user'] = DB::table('organization_user')->where('organization_id', '<>', $id)->count();
        $counts['users'] = DB::table('users')->count();
        $counts['organizations'] = DB::table('organizations')->count();

        return $counts;
    }

    /** @return list<int> */
    private function personaIds(): array
    {
        return User::query()->where('is_demo', true)->whereIn('email', array_column(self::PERSONAS, 0))->pluck('id')->map(fn ($v) => (int) $v)->all();
    }

    /** Персонажи по ролям; членства на месте (если кто-то пропал — вернуть) */
    private function personas(Organization $org): array
    {
        $personas = [];
        foreach (self::PERSONAS as $key => [$email, $name, $role]) {
            $user = User::query()->where('is_demo', true)->where('email', $email)->first()
                ?? throw new RuntimeException("Нет персонажа демо {$email} — пересоздайте демо (--destroy --force, затем --create)");
            if ($user->roleIn($org) !== $role) {
                $org->addMember($user, $role);
                $user->forgetMemberships();
            }
            $personas[$key] = $user;
        }

        return $personas;
    }

    /** После транзакции: кэш настроек, файлы демо (их там нет, но вдруг), старые демо-сессии */
    private function afterPurge(int $id): void
    {
        if ($id <= 0) {
            return;
        }
        Cache::forget('settings:'.$id);
        foreach (['receipts', 'documents'] as $root) {
            Storage::disk('local')->deleteDirectory($root.'/'.$id);
        }
        Demo::pruneSessions();
    }
}
