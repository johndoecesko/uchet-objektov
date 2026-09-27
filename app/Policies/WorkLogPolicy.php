<?php

namespace App\Policies;

class WorkLogPolicy extends RolePolicy
{
    protected bool $foremanView = true;

    protected bool $foremanCreate = true;

    protected bool $foremanEditOwn = true;
}
