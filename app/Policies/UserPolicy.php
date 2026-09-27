<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class UserPolicy extends RolePolicy
{
    protected bool $adminOnly = true;

    public function delete(User $user, Model $record): bool
    {
        return $user->isAdmin() && $user->id !== $record->getKey();
    }
}
