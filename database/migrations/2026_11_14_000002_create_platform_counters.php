<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Счётчики платформы по дням (App\Support\PlatformCounter): входы в демо, переходы к регистрации из демо и т. п. */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('platform_counters')) {
            Schema::create('platform_counters', function (Blueprint $table) {
                $table->id();
                $table->date('date');
                $table->string('key', 50);
                $table->unsignedInteger('count')->default(0);
                $table->unique(['date', 'key']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_counters');
    }
};
