<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Заблокированная организация не открывается — понятное сообщение вместо данных */
class EnsureOrganizationIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $org = Filament::getTenant();

        if ($org instanceof Organization && $org->isBlocked()) {
            abort(403, "Организация «{$org->name}» заблокирована. Если это ошибка — напишите в поддержку: ".config('app.support_email'));
        }

        return $next($request);
    }
}
