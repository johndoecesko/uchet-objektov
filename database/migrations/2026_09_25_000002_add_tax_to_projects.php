<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->decimal('tax_percent', 5, 2)->default(0)->after('contract_amount');
        });

        Schema::table('expense_categories', function (Blueprint $table) {
            $table->boolean('is_tax')->default(false)->after('is_labor');
        });

        if (! DB::table('expense_categories')->where('is_tax', true)->exists()) {
            DB::table('expense_categories')->insert([
                'name' => 'Налоги (% от договора)',
                'is_labor' => false,
                'is_tax' => true,
                'sort' => 85,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('expense_categories')->where('is_tax', true)->delete();
        Schema::table('expense_categories', fn (Blueprint $table) => $table->dropColumn('is_tax'));
        Schema::table('projects', fn (Blueprint $table) => $table->dropColumn('tax_percent'));
    }
};
