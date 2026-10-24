<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * С этого шага панель требует подтверждённую почту. Учётки, заведённые до него, создавал администратор вручную —
 * считаем их почту подтверждённой, иначе их не пустит в панель.
 */
return new class extends Migration
{
    public function up(): void
    {
        DB::table('users')->whereNull('email_verified_at')->update(['email_verified_at' => DB::raw('created_at')]);
        DB::table('users')->whereNull('email_verified_at')->update(['email_verified_at' => now()]);
    }

    public function down(): void
    {
        // Отметки не снимаем: какие были поставлены этой миграцией, уже не отличить
    }
};
