<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('customer_inn', 12)->nullable()->after('customer');
            $table->string('customer_kpp', 9)->nullable()->after('customer_inn');
            $table->string('customer_ogrn', 15)->nullable()->after('customer_kpp');
            $table->string('customer_address')->nullable()->after('customer_ogrn');
            $table->index('customer_inn');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['customer_inn']);
            $table->dropColumn(['customer_inn', 'customer_kpp', 'customer_ogrn', 'customer_address']);
        });
    }
};
