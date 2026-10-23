<?php

namespace App\Providers;

use App\Models\Activity;
use App\Models\Organization;
use App\Models\User;
use App\Policies\ActivityPolicy;
use App\Support\CurrentOrganization;
use Filament\Events\TenantSet;
use Illuminate\Auth\Events\Login;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Текущая организация — своя на каждый запрос и каждую задачу очереди
        $this->app->scoped(CurrentOrganization::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Модель истории изменений лежит в пакете — политику регистрируем явно
        Gate::policy(Activity::class, ActivityPolicy::class);

        // Последняя открытая организация — она откроется по умолчанию (и по ярлыкам /go/…)
        Event::listen(TenantSet::class, function (TenantSet $event) {
            $user = $event->getUser();
            $org = $event->getTenant();
            if ($user instanceof User && $org instanceof Organization && (int) $user->current_organization_id !== (int) $org->getKey()) {
                User::whereKey($user->getKey())->update(['current_organization_id' => $org->getKey()]);
                $user->current_organization_id = $org->getKey();
            }
        });

        // Время последнего входа — для служебной сводки администратора платформы
        Event::listen(Login::class, function (Login $event) {
            if ($event->user instanceof User) {
                User::whereKey($event->user->getKey())->update(['last_login_at' => now()]);
            }
        });
    }
}
