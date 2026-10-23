<?php

namespace App\Policies;

/** Приглашения: создаёт и отзывает только администратор организации */
class InvitationPolicy extends RolePolicy
{
    protected bool $adminOnly = true;
}
