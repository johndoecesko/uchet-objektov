<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use App\Support\CurrentOrganization;
use App\Support\Demo\DemoData;
use Illuminate\Database\Seeder;

/**
 * Локальные демо-данные со входом по паролю (скриншоты, ручная проверка): вымышленная организация
 * «Демо-Монтаж» (/app/demo-montazh), 4 объекта, 5 сотрудников. Данные — App\Support\Demo\DemoData.
 * Это НЕ публичное демо (то — app:demo-reset, без паролей, адрес /app/demo).
 *
 *   php artisan db:seed --class=DemoSeeder
 *
 * Входы (пароль у всех — demo12345):
 *   admin@demo.local    — администратор
 *   manager@demo.local  — менеджер / бухгалтер
 *   foreman@demo.local  — прораб / инженер
 */
class DemoSeeder extends Seeder
{
    public const PASSWORD = 'demo12345';

    public const SLUG = 'demo-montazh';

    public function run(): void
    {
        if (app()->isProduction() || Organization::where('slug', self::SLUG)->exists()) {
            $this->command?->error('Демо-организация уже есть (или это рабочий сервер) — демо-данные не добавлены.');

            return;
        }

        $org = Organization::create(['name' => 'ООО «Демо-Монтаж»', 'slug' => self::SLUG, 'inn' => '6400000000']);

        $personas = [];
        foreach ([
            ['admin@demo.local', 'Анна Директорова', UserRole::Admin],
            ['manager@demo.local', 'Марина Счётова', UserRole::Manager],
            ['foreman@demo.local', 'Игорь Монтажов', UserRole::Foreman],
        ] as [$email, $name, $role]) {
            $user = User::updateOrCreate(['email' => $email], ['name' => $name, 'password' => self::PASSWORD, 'is_active' => true]);
            $user->forceFill(['email_verified_at' => now(), 'current_organization_id' => $org->id])->save();
            $org->addMember($user, $role);
            $personas[$role->value] = $user;
        }

        app(CurrentOrganization::class)->run($org, fn () => (new DemoData($org))->fill($personas));
    }
}
