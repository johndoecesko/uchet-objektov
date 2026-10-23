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
            session()->put('url.intended', $request->fullUrl());

            return new RedirectResponse(url('/app/login'));
        }

        $org = $user->getDefaultTenant(Filament::getPanel('admin'));
        if (! $org) {
            return new RedirectResponse(url('/app')); // организаций нет — Filament предложит создать
        }

        return new RedirectResponse(url('/app/'.$org->slug.($path ? '/'.trim($path, '/') : '')));
    }
}
