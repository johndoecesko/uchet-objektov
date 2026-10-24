<?php

use App\Http\Controllers\GoToOrganization;
use App\Http\Controllers\InvitationController;
use Illuminate\Support\Facades\Route;

// Учёт — панель Filament на /app (App\Providers\Filament\AdminPanelProvider), организация в адресе: /app/{slug}/…
// Администратор платформы — /platform (App\Providers\Filament\PlatformPanelProvider)

// Публичная часть: страница сервиса и документы (тексты — resources/views/legal, версия — config/legal.php)
// Вошедшему главная не нужна — сразу в учёт (в последнюю открытую организацию)
Route::get('/', fn () => auth()->check() ? redirect('/app') : view('landing'))->name('home');
Route::view('/privacy', 'legal.privacy')->name('privacy');
Route::view('/terms', 'legal.terms')->name('terms');
Route::view('/consent', 'legal.consent')->name('consent');

// «В мою организацию»: /go/expenses/create → /app/{организация}/expenses/create (ярлыки PWA, старые ссылки)
Route::get('/go/{path?}', GoToOrganization::class)->where('path', '[a-z0-9\-/]*')->name('go');

// Старые адреса (до организаций панель была в корне сайта) — постоянный редирект
Route::permanentRedirect('/login', '/app/login');
Route::get('/{section}/{rest?}', fn (string $section, ?string $rest = null) => redirect(
    '/go/'.($section === 'users' ? 'members' : $section).($rest ? '/'.$rest : ''), 301,
))->where('section', 'projects|expenses|work-logs|incomes|payouts|advances|employees|expense-categories|users|history|settings')
    ->where('rest', '[a-z0-9\-/]*');

// Приглашение в организацию: ссылка подписанная, 7 дней, одноразовая
Route::get('/invite/{invitation}', [InvitationController::class, 'show'])->name('invite.show');
Route::post('/invite/{invitation}', [InvitationController::class, 'accept'])->middleware('auth')->name('invite.accept');
