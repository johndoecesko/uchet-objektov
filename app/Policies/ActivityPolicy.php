<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/** История изменений: смотрит только админ, править и удалять записи истории нельзя никому */
class ActivityPolicy extends RolePolicy
{
    protected bool $adminOnly = true;

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, Model $record): bool
    {
        return false;
    }

    public function delete(User $user, Model $record): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
