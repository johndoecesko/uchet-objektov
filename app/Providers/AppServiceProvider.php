<?php

namespace App\Providers;

use App\Models\Activity;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\Auth;
use App\Policies\ActivityPolicy;
use App\Support\CurrentOrganization;
use App\Support\Demo\Demo;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Filament\Auth\Notifications as FilamentAuth;
use Filament\Events\TenantSet;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\NotPwnedVerifier;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Текущая организация — своя на каждый запрос и каждую задачу очереди
        $this->app->scoped(CurrentOrganization::class);

        // Проверка пароля по утечкам: ждём ответа не дольше 5 секунд, а не 30 по умолчанию
        $this->app->extend(UncompromisedVerifier::class, fn ($verifier, $app) => new NotPwnedVerifier($app[HttpFactory::class], 5));

        // Письма входа — свои, на русском (Filament берёт их из контейнера)
        $this->app->bind(FilamentAuth\VerifyEmail::class, Auth\VerifyEmail::class);
        $this->app->bind(FilamentAuth\VerifyEmailChange::class, Auth\VerifyEmailChange::class);
        $this->app->bind(FilamentAuth\ResetPassword::class, Auth\ResetPassword::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Модель истории изменений лежит в пакете — политику регистрируем явно
        Gate::policy(Activity::class, ActivityPolicy::class);

        // Демо только для чтения: демо-пользователю разрешено лишь смотреть — белый список закрывает и reorder,
        // и способности, которых нет в политиках (Filament тогда спрашивает только Gate::before)
        Gate::before(fn ($user, string $ability) => $user instanceof User && $user->is_demo
            && ! in_array($ability, ['viewAny', 'view'], true) ? false : null);

        // Вход в демо: с одного IP — 5 в минуту и 30 в час, всего — 600 в час
        RateLimiter::for('demo-enter', fn (Request $request) => [
            Limit::perMinute(5)->by('demo-enter-ip-m:'.$request->ip()),
            Limit::perHour(30)->by('demo-enter-ip-h:'.$request->ip()),
            Limit::perHour(600)->by('demo-enter-all'),
        ]);

        // Пароль — от 8 символов (регистрация, сброс, профиль). На сервере — ещё и не из утечек (api.pwnedpasswords.com
        // получает только 5 первых знаков хеша; сервис недоступен — проверка пропускается)
        Password::defaults(fn () => app()->isProduction() ? Password::min(8)->max(100)->uncompromised() : Password::min(8)->max(100));

        // Последняя открытая организация — она откроется по умолчанию (и по ярлыкам /go/…)
        Event::listen(TenantSet::class, function (TenantSet $event) {
            $user = $event->getUser();
            $org = $event->getTenant();
            if ($user instanceof User && ! $user->is_demo && $org instanceof Organization && (int) $user->current_organization_id !== (int) $org->getKey()) {
                User::whereKey($user->getKey())->update(['current_organization_id' => $org->getKey()]);
                $user->current_organization_id = $org->getKey();
            }
        });

        // Пароль задан через «Забыли пароль?» — учётка из соцсети теперь может входить и по паролю
        Event::listen(PasswordReset::class, function (PasswordReset $event) {
            if ($event->user instanceof User && ! $event->user->has_password) {
                $event->user->forceFill(['has_password' => true])->save();
            }
        });

        // Время последнего входа — для служебной сводки администратора платформы
        Event::listen(Login::class, function (Login $event) {
            if ($event->user instanceof User && ! $event->user->is_demo) {
                User::whereKey($event->user->getKey())->update(['last_login_at' => now()]);
            }
        });
    }
}
