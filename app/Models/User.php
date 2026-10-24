<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Concerns\LogsChanges;
use App\Support\CurrentOrganization;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasDefaultTenant;
use Filament\Models\Contracts\HasTenants;
use Filament\Panel;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;

/**
 * Пользователь платформы. Роль — не у пользователя, а в каждой организации (Membership).
 * is_active — общий доступ к сервису (блокирует администратор платформы).
 * Почта подтверждается письмом (кроме пришедших по почтовому приглашению); без подтверждения панель не открывается.
 */
class User extends Authenticatable implements FilamentUser, HasDefaultTenant, HasTenants, MustVerifyEmail
{
    use HasFactory, LogsChanges, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'is_active', 'phone'];

    protected $hidden = ['password', 'remember_token'];

    protected $attributes = ['is_active' => true];

    /** Членства по организациям в пределах запроса: [organization_id => Membership|null] */
    private array $membershipCache = [];

    /** Пароль в историю изменений не пишем */
    protected function activityExcept(): array
    {
        return ['password', 'remember_token'];
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'last_login_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
            'is_platform_admin' => 'boolean',
            'max_user_id' => 'integer',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        if (! ($this->is_active ?? true)) {
            return false;
        }

        return $panel->getId() === 'platform' ? (bool) $this->is_platform_admin : true;
    }

    public function organizations(): BelongsToMany
    {
        return $this->belongsToMany(Organization::class)->withPivot(['role', 'is_active'])->withTimestamps();
    }

    /** Все членства пользователя во всех организациях (без ограничения текущей) */
    public function memberships(): HasMany
    {
        return $this->hasMany(Membership::class)->withoutGlobalScopes(['organization', Membership::FILAMENT_TENANCY_SCOPE]);
    }

    public function membershipIn(Organization|int|null $organization): ?Membership
    {
        $id = $organization instanceof Organization ? $organization->getKey() : $organization;
        if (! $id || ! $this->exists) {
            return null;
        }

        if (! array_key_exists($id, $this->membershipCache)) {
            $this->membershipCache[$id] = Membership::acrossOrganizations()
                ->where('organization_id', $id)->where('user_id', $this->id)->first();
        }

        return $this->membershipCache[$id];
    }

    public function forgetMemberships(): static
    {
        $this->membershipCache = [];

        return $this;
    }

    /** Роль в организации (null — не участник или доступ выключен) */
    public function roleIn(Organization|int|null $organization): ?UserRole
    {
        $m = $this->membershipIn($organization);

        return $m && $m->is_active ? $m->role : null;
    }

    /** Роль в текущей организации */
    public function currentRole(): ?UserRole
    {
        return $this->roleIn(app(CurrentOrganization::class)->id());
    }

    public function hasRole(UserRole ...$roles): bool
    {
        return in_array($this->currentRole(), $roles, true);
    }

    public function isAdmin(): bool
    {
        return $this->currentRole() === UserRole::Admin;
    }

    /** Админ и менеджер видят финансы целиком */
    public function isOffice(): bool
    {
        return $this->hasRole(UserRole::Admin, UserRole::Manager);
    }

    // Мультитенантность Filament

    /** Организации для переключателя: где членство активно */
    public function getTenants(Panel $panel): Collection
    {
        return $this->organizations()->wherePivot('is_active', true)->orderBy('name')->get();
    }

    /** Заблокированную организацию пускаем до проверки статуса — там понятное сообщение, а не 404 */
    public function canAccessTenant(Model $tenant): bool
    {
        return $tenant instanceof Organization && $this->roleIn($tenant) !== null;
    }

    /** По умолчанию — организация, открытая последней; иначе первая работающая */
    public function getDefaultTenant(Panel $panel): ?Model
    {
        $orgs = $this->getTenants($panel);

        return $orgs->firstWhere('id', $this->current_organization_id)
            ?? $orgs->first(fn (Organization $o) => ! $o->isBlocked())
            ?? $orgs->first();
    }

    /** Сотрудник этого пользователя в текущей организации */
    public function employee()
    {
        return $this->hasOne(Employee::class);
    }
}
