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
        $password = $this->secret('Пароль (мин. 8 символов)');
        if (strlen((string) $password) < 8) {
            $this->error('Пароль слишком короткий');

            return self::FAILURE;
        }

        $user = User::updateOrCreate(
            ['email' => $this->argument('email')],
            ['name' => $this->argument('name'), 'password' => $password, 'role' => UserRole::Admin, 'is_active' => true],
        );

        $this->info("Готово: {$user->email} — администратор");

        return self::SUCCESS;
    }
}
