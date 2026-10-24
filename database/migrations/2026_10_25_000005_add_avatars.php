<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Фото профиля и логотип организации: пути на закрытом диске (local), показываются по подписанной ссылке (App\Support\Avatar) */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'avatar_path')) {
            Schema::table('users', function (Blueprint $table) {
                $table->string('avatar_path')->nullable()->after('phone');
            });
        }
        if (! Schema::hasColumn('organizations', 'logo_path')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->string('logo_path')->nullable()->after('inn');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'avatar_path')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('avatar_path');
            });
        }
        if (Schema::hasColumn('organizations', 'logo_path')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->dropColumn('logo_path');
            });
        }
    }
};
