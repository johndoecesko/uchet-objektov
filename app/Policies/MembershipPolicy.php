<?php

namespace App\Policies;

use App\Enums\UserRole;
use App\Models\Membership;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** Участники: только администратор организации. Себя убрать нельзя; последнего администратора — тоже */
class MembershipPolicy extends RolePolicy
{
    protected bool $adminOnly = true;

    public function create(User $user): bool
    {
        return false; // участники добавляются только через приглашения
    }

    public function delete(User $user, Model $record): bool
    {
        /** @var Membership $record */
        return $user->isAdmin()
            && (int) $record->user_id !== (int) $user->id
            && ($record->role !== UserRole::Admin || Membership::otherActiveAdminExists($record));
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
