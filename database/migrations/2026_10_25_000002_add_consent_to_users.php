<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Согласие с Политикой и Соглашением при регистрации: когда, с какой редакцией (config legal.version), с какого IP */
return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('users', 'consent_at')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->timestamp('consent_at')->nullable()->after('email_verified_at');
            $table->string('consent_version', 20)->nullable()->after('consent_at');
            $table->string('consent_ip', 45)->nullable()->after('consent_version');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['consent_at', 'consent_version', 'consent_ip']);
        });
    }
};
