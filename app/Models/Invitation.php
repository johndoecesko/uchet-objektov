<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Concerns\BelongsToOrganization;
use App\Models\Concerns\TracksCreator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\URL;

/**
 * Приглашение в организацию: по почте (email задан — принять может только владелец этой почты)
 * или ссылкой для мессенджера (без email). Одноразовое, действует 7 дней. Ссылка — подписанная.
 */
class Invitation extends Model
{
    use BelongsToOrganization, TracksCreator;

    public const TTL_DAYS = 7;

    protected $fillable = ['email', 'role', 'expires_at'];

    protected function casts(): array
    {
        return ['role' => UserRole::class, 'expires_at' => 'datetime', 'accepted_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::creating(function (Invitation $i) {
            $i->expires_at ??= now()->addDays(self::TTL_DAYS);
            $i->email = $i->email ? mb_strtolower(trim($i->email)) : null;
        });
    }

    public function acceptedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'accepted_by');
    }

    public function scopePending(Builder $q): Builder
    {
        return $q->whereNull('accepted_at')->where('expires_at', '>', now());
    }

    public function isPending(): bool
    {
        return $this->accepted_at === null && $this->expires_at->isFuture();
    }

    /** Подписанная ссылка на страницу приглашения */
    public function url(): string
    {
        return URL::temporarySignedRoute('invite.show', $this->expires_at, ['invitation' => $this->id]);
    }

    /** Подходит ли приглашение этому пользователю (для приглашения по почте — только владельцу почты) */
    public function allows(User $user): bool
    {
        return $this->email === null || mb_strtolower($user->email) === $this->email;
    }
}
