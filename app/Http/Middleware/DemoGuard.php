<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Demo\Demo;
use Closure;
use Filament\Notifications\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Демо-пользователь: закрыть маршруты, где можно что-то записать или привязать (загрузка файлов, приглашения,
 * соцсети, профиль, новая организация, профиль организации, платформа), и не пускать в индекс поисковиков.
 * Демо выключили (DEMO_ENABLED=false) — демо-сессия разлогинивается.
 * Стоит в группе web (Livewire, приглашения, соцсети) и в панели учёта.
 */
class DemoGuard
{
    /** Имена маршрутов, закрытые для демо (шаблоны Str::is) */
    public const BLOCKED = [
        'livewire.upload-file',
        'livewire.preview-file',
        'invite.show',
        'invite.accept',
        'social.*',
        'filament.admin.auth.profile',
        'filament.admin.auth.email-change-verification.*',
        'filament.admin.tenant.registration',
        'filament.admin.tenant.profile',
        'filament.platform.*',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User || ! $user->is_demo) {
            return $next($request);
        }

        if (! Demo::enabled()) {
            Auth::guard()->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect('/');
        }

        $name = (string) $request->route()?->getName();
        if ($name !== '' && Str::is(self::BLOCKED, $name)) {
            if ($request->isMethod('GET') && ! $request->expectsJson()) {
                Notification::make()->title('В демо это недоступно')->body('Зарегистрируйтесь, чтобы вести свой учёт.')->warning()->send();

                return redirect('/app/'.Demo::slug());
            }
            abort(403, 'В демо можно только смотреть');
        }

        $response = $next($request);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
