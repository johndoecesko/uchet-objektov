<?php

namespace App\Models;

use App\Enums\OrganizationStatus;
use App\Enums\UserRole;
use App\Support\Avatar;
use App\Support\Demo\Demo;
use App\Support\Demo\DemoReadOnly;
use Filament\Models\Contracts\HasAvatar;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/** Организация (тенант): у каждой свои объекты, люди, деньги, статьи затрат и настройки */
class Organization extends Model implements HasAvatar
{
    /** Статьи затрат новой организации: [название, is_labor, is_tax, sort] */
    public const DEFAULT_CATEGORIES = [
        ['Материалы и оборудование', false, false, 10],
        ['Работа (зарплата из журнала)', true, false, 20],
        ['Субподряд', false, false, 30],
        ['Транспорт и ГСМ', false, false, 40],
        ['Аренда техники и инструмента', false, false, 50],
        ['Проживание и командировки', false, false, 60],
        ['Инструмент и расходники', false, false, 70],
        ['Прочее', false, false, 80],
        ['Налоги (% от договора)', false, true, 85],
    ];

    /** Адреса панели, которые не могут быть адресом организации (/app/{slug}) */
    public const RESERVED_SLUGS = [
        'login', 'logout', 'register', 'registration', 'new', 'profile', 'password-reset', 'reset-password',
        'email-verification', 'email-change-verification', 'multi-factor-authentication', 'invite', 'api',
        'livewire', 'platform', 'app', 'go', 'auth', 'settings', 'admin', 'storage', 'up', 'demo',
    ];

    protected $fillable = ['name', 'slug', 'inn', 'status', 'logo_path'];

    protected $attributes = ['status' => 'active'];

    protected function casts(): array
    {
        // is_demo — не в $fillable: ставит только пересоздание демо (App\Support\Demo\DemoReset)
        return ['status' => OrganizationStatus::class, 'is_demo' => 'boolean'];
    }

    /** Логотип в переключателе организаций; нет логотипа — буквы названия */
    public function getFilamentAvatarUrl(): ?string
    {
        return Avatar::url($this->logo_path);
    }

    protected static function booted(): void
    {
        static::creating(function (Organization $org) {
            $org->slug = filled($org->slug) ? $org->slug : static::uniqueSlug($org->name);
            if (empty($org->created_by) && auth()->check()) {
                $org->created_by = auth()->id();
            }
        });

        // Новая организация получает стандартный справочник статей затрат
        static::created(fn (Organization $org) => $org->seedDefaults());
        // Демо: организацию не меняет никто, кроме пересоздания; признак демо не ставится и не снимается вручную
        static::saving(function (Organization $org) {
            if ($org->isDirty('is_demo') && ($org->exists || $org->is_demo) && ! Demo::writesAllowed()) {
                throw new DemoReadOnly('Признак демо меняет только app:demo-reset');
            }
            Demo::assertWritable($org);
        });
        static::deleting(fn (Organization $org) => Demo::assertWritable($org));
        static::saving(fn (Organization $org) => Avatar::forgetOld($org, 'logo_path'));
        static::deleted(fn (Organization $org) => Avatar::forgetOld($org, 'logo_path', deleted: true));
    }

    /** Настоящие организации (без демо) — для статистики и служебных выборок */
    public function scopeReal(Builder $query): Builder
    {
        return $query->where($query->qualifyColumn('is_demo'), false);
    }

    public function seedDefaults(): void
    {
        foreach (self::DEFAULT_CATEGORIES as [$name, $labor, $tax, $sort]) {
            if (ExpenseCategory::acrossOrganizations()->where('organization_id', $this->id)->where('name', $name)->exists()) {
                continue;
            }
            $category = new ExpenseCategory(['name' => $name, 'is_labor' => $labor, 'is_tax' => $tax, 'sort' => $sort, 'is_active' => true]);
            $category->organization_id = $this->id; // не через fill: organization_id из форм не принимается
            $category->save();
        }
    }

    /** Адрес для URL: транслит названия, уникальный, не из зарезервированных */
    public static function uniqueSlug(string $name): string
    {
        $base = Str::limit(Str::slug(self::translit($name)), 40, '') ?: 'org';
        $base = trim($base, '-') ?: 'org';
        $slug = $base;
        for ($i = 2; in_array($slug, self::RESERVED_SLUGS, true) || static::where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    private static function translit(string $s): string
    {
        static $map = [
            'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd', 'е' => 'e', 'ё' => 'e', 'ж' => 'zh', 'з' => 'z',
            'и' => 'i', 'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n', 'о' => 'o', 'п' => 'p', 'р' => 'r',
            'с' => 's', 'т' => 't', 'у' => 'u', 'ф' => 'f', 'х' => 'kh', 'ц' => 'ts', 'ч' => 'ch', 'ш' => 'sh', 'щ' => 'shch',
            'ъ' => '', 'ы' => 'y', 'ь' => '', 'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
        ];

        return strtr(mb_strtolower($s), $map);
    }

    public function isBlocked(): bool
    {
        return $this->status === OrganizationStatus::Blocked;
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot(['role', 'is_active'])->withTimestamps();
    }

    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class);
    }

    /** Добавить пользователя (или обновить роль, если уже есть) */
    public function addMember(User $user, UserRole $role, bool $active = true): Membership
    {
        return Membership::acrossOrganizations()->updateOrCreate(
            ['organization_id' => $this->id, 'user_id' => $user->id],
            ['role' => $role, 'is_active' => $active],
        );
    }

    // Связи для Filament (тенант → записи ресурса)

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function projectBudgets(): HasMany
    {
        return $this->hasMany(ProjectBudget::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }

    public function expenses(): HasMany
    {
        return $this->hasMany(Expense::class);
    }

    public function expenseCategories(): HasMany
    {
        return $this->hasMany(ExpenseCategory::class);
    }

    public function workLogs(): HasMany
    {
        return $this->hasMany(WorkLog::class);
    }

    public function payouts(): HasMany
    {
        return $this->hasMany(Payout::class);
    }

    public function advances(): HasMany
    {
        return $this->hasMany(Advance::class);
    }

    public function incomes(): HasMany
    {
        return $this->hasMany(Income::class);
    }

    public function settings(): HasMany
    {
        return $this->hasMany(Setting::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(Activity::class);
    }

    public function invitations(): HasMany
    {
        return $this->hasMany(Invitation::class);
    }
}
