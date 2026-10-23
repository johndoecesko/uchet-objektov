<?php

namespace App\Policies;

use App\Models\User;

/** Учётные записи целиком — только администратор платформы (заблокировать / разблокировать). В организации — «Участники» */
class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->is_platform_admin;
    }

    public function view(User $user, User $record): bool
    {
        return (bool) $user->is_platform_admin;
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, User $record): bool
    {
        return (bool) $user->is_platform_admin;
    }

    public function delete(User $user, User $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
