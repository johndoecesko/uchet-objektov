<?php

namespace App\Http\Controllers;

use App\Models\Invitation;
use App\Support\Registration;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Страница приглашения: гостю — «войдите или зарегистрируйтесь», вошедшему — «принять».
 * После входа/регистрации Filament возвращает сюда же (url.intended).
 */
class InvitationController extends Controller
{
    public function show(Request $request, int $invitation): View|RedirectResponse
    {
        $invite = Invitation::acrossOrganizations()->with('organization')->find($invitation);

        if (! $invite || ! $request->hasValidSignature() || ! $invite->isPending() || $invite->organization->isBlocked()) {
            return view('invite', ['invite' => null, 'error' => 'Приглашение недействительно: ссылка устарела, уже использована или отозвана. Попросите администратора организации прислать новое.']);
        }

        Registration::rememberInvitation($invite);
        $user = $request->user();

        if (! $user) {
            session(['url.intended' => $request->fullUrl()]);

            return view('invite', ['invite' => $invite, 'error' => null]);
        }

        if (! $invite->allows($user)) {
            return view('invite', ['invite' => $invite, 'error' => "Это приглашение отправлено на {$invite->email}, а вы вошли как {$user->email}. Выйдите и войдите под нужной почтой."]);
        }

        if ($user->roleIn($invite->organization) !== null) {
            Registration::forgetInvitation();

            return redirect('/app/'.$invite->organization->slug);
        }

        return view('invite', ['invite' => $invite, 'error' => null]);
    }

    public function accept(Request $request, int $invitation): RedirectResponse
    {
        $user = $request->user();
        $invite = Registration::pendingInvitation();

        // Принять можно только приглашение, открытое по действующей подписанной ссылке в этой сессии
        abort_unless($invite && $invite->id === $invitation && $invite->allows($user) && ! $invite->organization->isBlocked(), 403);

        DB::transaction(function () use ($invite, $user) {
            $locked = Invitation::acrossOrganizations()->lockForUpdate()->find($invite->id);
            abort_unless($locked?->isPending(), 410, 'Приглашение уже использовано');

            if ($user->roleIn($invite->organization) === null) {
                $invite->organization->addMember($user, $invite->role);
            }
            $locked->forceFill(['accepted_at' => now(), 'accepted_by' => $user->id])->save();
        });

        Registration::forgetInvitation();
        $user->forgetMemberships()->forceFill(['current_organization_id' => $invite->organization_id])->saveQuietly();

        return redirect('/app/'.$invite->organization->slug);
    }
}
