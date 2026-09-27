<?php

namespace App\Policies;

class ExpenseCategoryPolicy extends RolePolicy
{
    protected bool $adminOnly = true;
}
