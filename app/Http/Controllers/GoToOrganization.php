<?php

namespace App\Http\Controllers;

use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** /go/{раздел} → тот же раздел в организации пользователя по умолчанию (последней открытой) */
class GoToOrganization extends Controller
{
    public function __invoke(Request $request, ?string $path = null): RedirectResponse
    {
        $user = $request->user();
        if (! $user) {
            return redirect()->guest('/app/login');
        }

        $org = $user->getDefaultTenant(Filament::getPanel('admin'));
        if (! $org) {
            return redirect('/app'); // организаций нет — Filament предложит создать
        }

        return redirect('/app/'.$org->slug.($path ? '/'.trim($path, '/') : ''));
    }
}
