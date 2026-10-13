<?php

namespace App\Console\Commands;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Console\Command;

class CreateAdmin extends Command
{
    protected $signature = 'app:admin {email} {name=Администратор}';

    protected $description = 'Создать или сделать администратором пользователя (пароль вводится скрыто)';

    public function handle(): int
    {
        // Проверяем до вставки: кириллица из консоли SSH (не та раскладка/кодировка) ломала запрос MySQL
        $email = trim((string) $this->argument('email'));
        if (! mb_check_encoding($email, 'UTF-8') || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            $this->error('Некорректный email: '.mb_convert_encoding($email, 'UTF-8', 'UTF-8').' — нужен адрес латиницей, например admin@example.ru');

            return self::FAILURE;
        }

        $password = $this->secret('Пароль (мин. 8 символов)');
        if (strlen((string) $password) < 8) {
            $this->error('Пароль слишком короткий');

            return self::FAILURE;
        }

        $user = User::updateOrCreate(
            ['email' => $email],
            ['name' => $this->argument('name'), 'password' => $password, 'role' => UserRole::Admin, 'is_active' => true],
        );

        $this->info("Готово: {$user->email} — администратор");

        return self::SUCCESS;
    }
}
