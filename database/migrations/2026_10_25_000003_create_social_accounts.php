<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Вход через Яндекс ID и VK ID: привязки учёток к аккаунтам провайдеров. Токены провайдеров не храним — не нужны.
 * users.has_password: false — учётка создана через соцсеть и пароля, которым можно войти, у неё нет
 * (последний способ входа не даём отвязать, пока пароль не задан).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('social_accounts')) {
            Schema::create('social_accounts', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('provider', 20);
                $table->string('provider_user_id', 64);
                $table->string('email')->nullable();
                $table->timestamps();
                $table->unique(['provider', 'provider_user_id']);
                $table->unique(['user_id', 'provider']);
            });
        }

        if (! Schema::hasColumn('users', 'has_password')) {
            Schema::table('users', function (Blueprint $table) {
                $table->boolean('has_password')->default(true)->after('password');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('social_accounts');
        if (Schema::hasColumn('users', 'has_password')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('has_password');
            });
        }
    }
};
