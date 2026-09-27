<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Базовая политика по ролям.
 * admin/manager — всё; foreman — только то, что разрешено в наследнике.
 */
abstract class RolePolicy
{
    /** Что прораб может видеть в списке */
    protected bool $foremanView = false;

    /** Может ли прораб создавать записи */
    protected bool $foremanCreate = false;

    /** Может ли прораб править свои записи (created_by) */
    protected bool $foremanEditOwn = false;

    /** Только админ (справочники, пользователи) */
    protected bool $adminOnly = false;

    protected function office(User $user): bool
    {
        return $this->adminOnly ? $user->isAdmin() : $user->isOffice();
    }

    protected function isForeman(User $user): bool
    {
        return $user->role === UserRole::Foreman;
    }

    protected function ownsRecord(User $user, Model $record): bool
    {
        return isset($record->created_by) && (int) $record->created_by === (int) $user->id;
    }

    public function viewAny(User $user): bool
    {
        return $this->office($user) || ($this->isForeman($user) && $this->foremanView);
    }

    public function view(User $user, Model $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $this->office($user) || ($this->isForeman($user) && $this->foremanCreate);
    }

    public function update(User $user, Model $record): bool
    {
        return $this->office($user)
            || ($this->isForeman($user) && $this->foremanEditOwn && $this->ownsRecord($user, $record));
    }

    public function delete(User $user, Model $record): bool
    {
        return $this->office($user);
    }

    public function deleteAny(User $user): bool
    {
        return $this->office($user);
    }
}
