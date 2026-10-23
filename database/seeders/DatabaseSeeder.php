<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

/**
 * Справочники теперь свои у каждой организации и создаются вместе с ней
 * (Organization::DEFAULT_CATEGORIES). Первый вход: php artisan app:admin you@example.ru,
 * затем на /app — «Создайте организацию». Демо-данные: php artisan db:seed --class=DemoSeeder
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        //
    }
}
