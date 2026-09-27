<?php

namespace App\Policies;

class EmployeePolicy extends RolePolicy
{
    protected bool $foremanView = true;
}
