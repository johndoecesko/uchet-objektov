<?php

namespace App\Models;

use App\Enums\UserRole;
use App\Models\Concerns\LogsChanges;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, LogsChanges, Notifiable;

    protected $fillable = ['name', 'email', 'password', 'role', 'is_active', 'phone'];

    protected $hidden = ['password', 'remember_token'];

    protected $attributes = ['role' => 'foreman', 'is_active' => true];

    /** Пароль в историю изменений не пишем */
    protected function activityExcept(): array
    {
        return ['password', 'remember_token'];
    }

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
            'max_user_id' => 'integer',
        ];
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return (bool) ($this->is_active ?? true);
    }

    public function hasRole(UserRole ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }

    /** Админ и менеджер видят финансы целиком */
    public function isOffice(): bool
    {
        return $this->hasRole(UserRole::Admin, UserRole::Manager);
    }

    public function employee()
    {
        return $this->hasOne(Employee::class);
    }
}
