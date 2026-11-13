<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Публичное демо (App\Support\Demo): признак демо-организации и её участников-персонажей, источник регистрации.
 * Признаки не заполняются из форм — их ставит только app:demo-reset --create.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('organizations', 'is_demo')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->boolean('is_demo')->default(false)->after('status')->index();
            });
        }
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'is_demo')) {
                $table->boolean('is_demo')->default(false)->after('is_platform_admin')->index();
            }
            if (! Schema::hasColumn('users', 'signup_source')) {
                $table->string('signup_source', 20)->nullable()->after('consent_ip');
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('organizations', 'is_demo')) {
            Schema::table('organizations', function (Blueprint $table) {
                $table->dropIndex(['is_demo']);
                $table->dropColumn('is_demo');
            });
        }
        foreach (['is_demo', 'signup_source'] as $column) {
            if (Schema::hasColumn('users', $column)) {
                Schema::table('users', function (Blueprint $table) use ($column) {
                    if ($column === 'is_demo') {
                        $table->dropIndex(['is_demo']);
                    }
                    $table->dropColumn($column);
                });
            }
        }
    }
};
