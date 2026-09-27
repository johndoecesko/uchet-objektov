<?php

namespace App\Policies;

class ExpensePolicy extends RolePolicy
{
    protected bool $foremanView = true;

    protected bool $foremanCreate = true;

    protected bool $foremanEditOwn = true;
}
