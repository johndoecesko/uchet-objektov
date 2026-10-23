<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Console\Command;

/** Аварийный доступ: создать или сбросить администратора платформы (и при желании — администратора организации) */
class CreateAdmin extends Command
{
    protected $signature = 'app:admin {email} {name=Администратор} {--org= : адрес (slug) организации — сделать в ней администратором}';

    protected $description = 'Создать или сбросить администратора платформы (пароль вводится скрыто)';

    public function handle(): int
    {
        // Проверяем до вставки: кириллица из консоли SSH (не та раскладка/кодировка) ломала запрос MySQL
        $email = mb_strtolower(trim((string) $this->argument('email')));
        if (! mb_check_encoding($email, 'UTF-8') || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('Некорректный email: '.mb_convert_encoding($email, 'UTF-8', 'UTF-8').' — нужен адрес латиницей, например admin@example.ru');

            return self::FAILURE;
        }

        $org = null;
        if ($slug = $this->option('org')) {
            $org = Organization::where('slug', $slug)->first();
            if (! $org) {
                $this->error("Организация с адресом «{$slug}» не найдена");

                return self::FAILURE;
            }
        }

        $password = $this->secret('Пароль (мин. 8 символов)');
        if (strlen((string) $password) < 8) {
            $this->error('Пароль слишком короткий');

            return self::FAILURE;
        }

        $user = User::firstOrNew(['email' => $email]);
        $user->fill(['name' => $user->exists ? $user->name : $this->argument('name'), 'password' => $password, 'is_active' => true]);
        $user->forceFill(['is_platform_admin' => true, 'email_verified_at' => $user->email_verified_at ?? now()])->save();

        $org?->addMember($user, UserRole::Admin);

        $this->info("Готово: {$user->email} — администратор платформы".($org ? " и администратор «{$org->name}»" : ''));

        return self::SUCCESS;
    }
}
