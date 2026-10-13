<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Корзина: удалённые записи хранятся с отметкой deleted_at и восстанавливаются из интерфейса
return new class extends Migration
{
    private array $tables = ['expenses', 'work_logs', 'incomes', 'payouts', 'advances'];

    public function up(): void
    {
        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->softDeletes();
                $table->index('deleted_at');
            });
        }
    }

    public function down(): void
    {
        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table) use ($name) {
                $table->dropIndex($name.'_deleted_at_index');
                $table->dropSoftDeletes();
            });
        }
    }
};
