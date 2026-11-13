<?php

namespace App\Http\Controllers;

use App\Support\Demo\Demo;
use App\Support\PlatformCounter;
use App\Support\Registration;
use App\Support\SmartCaptcha;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Публичное демо без регистрации.
 *   GET  /demo           — страница с кнопкой (и капчей, если DEMO_CAPTCHA)
 *   POST /demo           — вход в демо: от гостя не принимается ничего, кроме CSRF (и токена капчи);
 *                          входит всегда в одного и того же персонажа (Demo::entryUser со всеми проверками)
 *   POST /demo/register  — выйти из демо и перейти к регистрации (источник «demo» — в сводку /platform)
 *   POST /demo/exit      — выйти из демо на главную
 * Осознанное исключение из правила «никаких входов без пароля» (HANDOFF.md, правило 11) — решение пользователя 2026-11-13.
 */
class DemoController extends Controller
{
    public function show(Request $request): Response|RedirectResponse
    {
        abort_unless(Demo::enabled(), 404);
        if ($request->user()) {
            return new RedirectResponse(url($request->user()->is_demo ? '/app/'.Demo::slug() : '/app'));
        }

        return response()
            ->view('demo', ['captcha' => self::captcha(), 'registrationOpen' => Registration::open()])
            ->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function enter(Request $request): RedirectResponse
    {
        abort_unless(Demo::enabled(), 404);

        // Уже вошёл: настоящего пользователя не переключаем, демо — просто в демо
        if ($user = $request->user()) {
            return new RedirectResponse(url($user->is_demo ? '/app/'.Demo::slug() : '/app'));
        }

        if (self::captcha() && ! SmartCaptcha::passes($request->string('smart-token')->toString() ?: null, $request->ip())) {
            return redirect()->route('demo')->withErrors(['captcha' => 'Подтвердите, что вы не робот.']);
        }

        try {
            $demoUser = Demo::entryUser();
        } catch (Throwable $e) {
            Log::error('Демо: вход невозможен — '.$e->getMessage());
            abort(503, 'Демо временно недоступно. Попробуйте позже.');
        }

        Auth::guard(Filament::getPanel('admin')->getAuthGuard())->login($demoUser); // без «запомнить меня»
        $request->session()->regenerate();
        PlatformCounter::hit('demo.enter');

        return new RedirectResponse(url('/app/'.Demo::slug()));
    }

    public function register(Request $request): RedirectResponse
    {
        if ($request->user() && ! $request->user()->is_demo) {
            return new RedirectResponse(url('/app'));
        }
        $this->logoutDemo($request);

        session(['signup_source' => 'demo']);
        PlatformCounter::hit('demo.signup_click');

        return new RedirectResponse(url(Registration::open() ? '/app/register' : '/'));
    }

    public function exit(Request $request): RedirectResponse
    {
        $this->logoutDemo($request);

        return new RedirectResponse(url('/'));
    }

    private function logoutDemo(Request $request): void
    {
        if ($request->user()?->is_demo) {
            Auth::guard(Filament::getPanel('admin')->getAuthGuard())->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        }
    }

    private static function captcha(): bool
    {
        return (bool) config('demo.captcha') && SmartCaptcha::enabled();
    }
}
