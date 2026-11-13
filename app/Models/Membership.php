<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\LogsChanges;
use App\Support\OrganizationMismatch;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\Contracts\Activity;

/** Участник организации: пользователь и его роль в этой организации (таблица organization_user) */
class Membership extends Model
{
    use BelongsToOrganization, LogsChanges;

    protected $table = 'organization_user';

    protected $fillable = ['organization_id', 'user_id', 'role', 'is_active'];

    protected $attributes = ['is_active' => true];

    protected function casts(): array
    {
        return ['role' => UserRole::class, 'is_active' => 'boolean'];
    }

    protected function activityExcept(): array
    {
        return ['organization_id'];
    }

    protected static function booted(): void
    {
        // В демо-организации — только персонажи демо, персонажи демо — только в ней (запись проверяет ещё и BelongsToOrganization)
        static::saving(function (Membership $m) {
            $orgDemo = (bool) Organization::query()->whereKey($m->organization_id)->value('is_demo');
            $userDemo = (bool) User::query()->whereKey($m->user_id)->value('is_demo');
            if ($orgDemo !== $userDemo) {
                throw new OrganizationMismatch($userDemo
                    ? "Демо-пользователя {$m->user_id} нельзя добавить в организацию {$m->organization_id}"
                    : "Пользователя {$m->user_id} нельзя добавить в демо-организацию");
            }
        });

        // Убрали из организации — сотрудник этой организации больше не привязан к его учётной записи
        static::deleted(function (Membership $m) {
            Employee::acrossOrganizations()
                ->where('organization_id', $m->organization_id)
                ->where('user_id', $m->user_id)
                ->update(['user_id' => null]);
        });
    }

    /** В истории — чей это доступ */
    public function tapActivity(Activity $activity, string $eventName): void
    {
        $activity->properties = $activity->properties->put('user_id', $this->user_id);
    }

    /** Есть ли в организации другой администратор с доступом (последнего убрать или понизить нельзя) */
    public static function otherActiveAdminExists(Membership $m): bool
    {
        return static::acrossOrganizations()
            ->where('organization_id', $m->organization_id)
            ->whereKeyNot($m->getKey())
            ->where('role', UserRole::Admin->value)
            ->where('is_active', true)
            ->exists();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function getNameAttribute(): ?string
    {
        return $this->user?->name;
    }
}
