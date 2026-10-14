<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Вложения к поступлениям (платёжки, акты) и выплатам (чек перевода, расписка)
return new class extends Migration
{
    public function up(): void
    {
        foreach (['incomes', 'payouts'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->json('attachments')->nullable()->after('notes');
            });
        }
    }

    public function down(): void
    {
        foreach (['incomes', 'payouts'] as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn('attachments');
            });
        }
    }
};
