<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;

/**
 * Организации: создать может любой вошедший; профиль меняет её администратор;
 * список всех организаций и блокировку видит только администратор платформы.
 */
class OrganizationPolicy
{
    public function viewAny(User $user): bool
    {
        return (bool) $user->is_platform_admin;
    }

    public function view(User $user, Organization $org): bool
    {
        return $user->is_platform_admin || $user->roleIn($org) !== null;
    }

    public function create(User $user): bool
    {
        return (bool) $user->is_active;
    }

    public function update(User $user, Organization $org): bool
    {
        return $user->is_platform_admin || $user->roleIn($org) === UserRole::Admin;
    }

    public function delete(User $user, Organization $org): bool
    {
        return false; // удаление организации с данными — только вручную, по обращению
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
