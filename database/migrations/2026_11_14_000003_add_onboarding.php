<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Первые шаги после регистрации: пример данных помечается на корневых записях (объект, сотрудники) —
 * по ним «Удалить пример» находит всё остальное; «Скрыть» чек-лист — у участника (своё у каждого в каждой организации).
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (['projects', 'employees'] as $name) {
            if (! Schema::hasColumn($name, 'is_example')) {
                Schema::table($name, function (Blueprint $table) {
                    $table->boolean('is_example')->default(false)->index();
                });
            }
        }
        if (! Schema::hasColumn('organization_user', 'onboarding_hidden_at')) {
            Schema::table('organization_user', function (Blueprint $table) {
                $table->timestamp('onboarding_hidden_at')->nullable();
            });
        }
    }

    public function down(): void
    {
        foreach (['projects', 'employees'] as $name) {
            if (Schema::hasColumn($name, 'is_example')) {
                Schema::table($name, function (Blueprint $table) {
                    $table->dropIndex(['is_example']);
                    $table->dropColumn('is_example');
                });
            }
        }
        if (Schema::hasColumn('organization_user', 'onboarding_hidden_at')) {
            Schema::table('organization_user', function (Blueprint $table) {
                $table->dropColumn('onboarding_hidden_at');
            });
        }
    }
};
