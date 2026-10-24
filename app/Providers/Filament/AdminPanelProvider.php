<?php

namespace App\Providers\Filament;

use App\Filament\InitialsAvatarProvider;
use App\Filament\Auth\EditProfile;
use App\Filament\Pages\About;
use App\Filament\Auth\Login;
use App\Filament\Auth\Register;
use App\Filament\Auth\RequestPasswordReset;
use App\Filament\Pages\Tenancy\EditOrganizationProfile;
use App\Filament\Pages\Tenancy\RegisterOrganization;
use App\Http\Middleware\EnsureOrganizationIsActive;
use App\Models\Organization;
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
            ->path('app')
            ->login(Login::class)
            ->registration(Register::class)
            // Почта: подтверждение после регистрации, сброс пароля, смена почты в профиле — только по ссылке из письма
            ->emailVerification()
            ->passwordReset(RequestPasswordReset::class)
            ->emailChangeVerification()
            ->profile(EditProfile::class, isSimple: false)
            // Организации: /app/{slug}/…, переключатель вверху, своя роль в каждой
            ->tenant(Organization::class, slugAttribute: 'slug')
            ->tenantRegistration(RegisterOrganization::class)
            ->tenantProfile(EditOrganizationProfile::class)
            ->tenantMiddleware([EnsureOrganizationIsActive::class], isPersistent: true)
            ->searchableTenantMenu(false)
            // Название: «Финпрораб» — торговая марка, «Учёт объектов» — что это за программа
            ->brandName('Финпрораб')
            ->brandLogo(fn () => view('filament.brand'))
            ->brandLogoHeight('2.5rem')
            ->defaultAvatarProvider(InitialsAvatarProvider::class)
            ->favicon(asset('icons/favicon-32.png'))
            // PWA: устанавливается на телефон как приложение (Chrome → «Установить приложение»)
            ->renderHook(PanelsRenderHook::HEAD_END, fn (): HtmlString => new HtmlString(
                '<meta name="robots" content="noindex, nofollow">'
                .'<link rel="manifest" href="/manifest.json">'
                .'<meta name="theme-color" content="#2563eb">'
                .'<meta name="mobile-web-app-capable" content="yes">'
                .'<meta name="apple-mobile-web-app-capable" content="yes">'
                .'<meta name="apple-mobile-web-app-title" content="Финпрораб">'
                .'<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">'
            ))
            // Вход и регистрация через Яндекс ID / VK ID — под формой (кнопки есть, только если заданы ключи)
            ->renderHook(PanelsRenderHook::AUTH_LOGIN_FORM_AFTER, fn () => view('filament.social-buttons', ['label' => 'Войти']))
            ->renderHook(PanelsRenderHook::AUTH_REGISTER_FORM_AFTER, fn () => view('filament.social-buttons', ['label' => 'Зарегистрироваться']))
            ->renderHook(PanelsRenderHook::BODY_END, fn (): HtmlString => new HtmlString(
                "<script>if ('serviceWorker' in navigator) { window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js')); }</script>"
            ))
            ->userMenuItems([
                Action::make('about')
                    ->label('О программе и помощь')
                    ->icon('heroicon-o-question-mark-circle')
                    // страница внутри организации; на экранах без неё (создание организации, подтверждение почты) пункта нет
                    ->visible(fn () => filament()->getTenant() !== null)
                    ->url(fn () => filament()->getTenant() ? About::getUrl() : null),
                Action::make('install')
                    ->label('Установить на телефон')
                    ->icon('heroicon-o-device-phone-mobile')
                    ->url('/install', shouldOpenInNewTab: true),
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
