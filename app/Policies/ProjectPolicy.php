<?php

namespace App\Policies;

class ProjectPolicy extends RolePolicy
{
    protected bool $foremanView = true;
}
