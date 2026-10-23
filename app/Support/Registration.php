<?php

namespace App\Support;

use App\Models\Invitation;

/**
 * Можно ли регистрироваться: открытая регистрация (REGISTRATION_ENABLED)
 * или в сессии лежит действующее приглашение (открыли ссылку-приглашение).
 */
class Registration
{
    public const SESSION_KEY = 'invitation_id';

    public static function open(): bool
    {
        return (bool) config('auth.registration_enabled');
    }

    public static function allowed(): bool
    {
        return self::open() || self::pendingInvitation() !== null;
    }

    /** Приглашение из сессии, если ещё действует */
    public static function pendingInvitation(): ?Invitation
    {
        $id = session(self::SESSION_KEY);
        if (! $id) {
            return null;
        }

        $invitation = Invitation::acrossOrganizations()->with('organization')->find($id);

        return $invitation?->isPending() ? $invitation : null;
    }

    public static function rememberInvitation(Invitation $invitation): void
    {
        session([self::SESSION_KEY => $invitation->id]);
    }

    public static function forgetInvitation(): void
    {
        session()->forget(self::SESSION_KEY);
    }
}
