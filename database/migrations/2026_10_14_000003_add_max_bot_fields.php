<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Бот в MAX: привязка аккаунта MAX к пользователю и отметка «расход пришёл из MAX»
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('max_user_id')->nullable()->unique()->after('phone');
        });

        Schema::table('expenses', function (Blueprint $table) {
            $table->string('source', 10)->default('web')->after('attachments');
        });
    }

    public function down(): void
    {
        Schema::table('expenses', function (Blueprint $table) {
            $table->dropColumn('source');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['max_user_id']);
            $table->dropColumn('max_user_id');
        });
    }
};
