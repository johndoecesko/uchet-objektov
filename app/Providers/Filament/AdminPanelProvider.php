<?php

namespace App\Providers\Filament;

use App\Support\Max\MaxApi;
use App\Support\Max\MaxBot;
use Filament\Actions\Action;
use Filament\Http\Middleware\Authenticate;
use Filament\Http\Middleware\AuthenticateSession;
use Filament\Http\Middleware\DisableBladeIconComponents;
use Filament\Http\Middleware\DispatchServingFilamentEvent;
use Filament\Notifications\Notification;
use Filament\Pages\Dashboard;
use Filament\Panel;
use Filament\PanelProvider;
use Filament\Support\Colors\Color;
use Filament\View\PanelsRenderHook;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Routing\Middleware\SubstituteBindings;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\HtmlString;
use Illuminate\View\Middleware\ShareErrorsFromSession;

class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('')
            ->login()
            ->brandName('Учёт объектов')
            ->favicon(asset('icons/favicon-32.png'))
            // PWA: устанавливается на телефон как приложение (Chrome → «Установить приложение»)
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): HtmlString => new HtmlString(
                '<link rel="manifest" href="/manifest.json">'
                .'<meta name="theme-color" content="#2563eb">'
                .'<meta name="mobile-web-app-capable" content="yes">'
                .'<meta name="apple-mobile-web-app-capable" content="yes">'
                .'<meta name="apple-mobile-web-app-title" content="Учёт">'
                .'<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">'
            ))
            ->renderHook(PanelsRenderHook::BODY_END, fn (): HtmlString => new HtmlString(
                "<script>if ('serviceWorker' in navigator) { window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js')); }</script>"
            ))
            ->userMenuItems([
                // Бот MAX: одноразовый код для привязки (показывается, только если бот настроен)
                Action::make('maxLink')
                    ->label(fn () => auth()->user()?->max_user_id ? 'MAX подключён — отключить' : 'Подключить MAX')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->visible(fn () => MaxApi::enabled())
                    ->requiresConfirmation(fn () => (bool) auth()->user()?->max_user_id)
                    ->modalHeading('Отключить MAX?')
                    ->modalDescription('Бот перестанет принимать от вас чеки, пока не подключите снова.')
                    ->action(function () {
                        $user = auth()->user();
                        if ($user->max_user_id) {
                            $user->forceFill(['max_user_id' => null])->save();
                            Notification::make()->title('MAX отключён')->success()->send();

                            return;
                        }
                        Notification::make()
                            ->title('Код для MAX: '.MaxBot::linkCode($user))
                            ->body('Отправьте этот код боту в MAX в течение 10 минут. После этого присылайте боту фото чеков.')
                            ->persistent()
                            ->success()
                            ->send();
                    }),
            ])
            ->colors(['primary' => Color::Blue])
            ->maxContentWidth('full')
            ->sidebarCollapsibleOnDesktop()
            ->navigationGroups(['Учёт', 'Люди и деньги', 'Настройки'])
            ->discoverResources(in: app_path('Filament/Resources'), for: 'App\Filament\Resources')
            ->discoverPages(in: app_path('Filament/Pages'), for: 'App\Filament\Pages')
            ->pages([Dashboard::class])
            ->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\Filament\Widgets')
            ->widgets([])
            ->middleware([
                EncryptCookies::class,
                AddQueuedCookiesToResponse::class,
                StartSession::class,
                AuthenticateSession::class,
                ShareErrorsFromSession::class,
                VerifyCsrfToken::class,
                SubstituteBindings::class,
                DisableBladeIconComponents::class,
                DispatchServingFilamentEvent::class,
            ])
            ->authMiddleware([Authenticate::class]);
    }
}
