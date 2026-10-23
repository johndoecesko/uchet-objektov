<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Организации (тенанты), членство с ролью в организации, приглашения, флаг администратора платформы
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('organizations')) {
            Schema::create('organizations', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug', 60)->unique();
                $table->string('inn', 12)->nullable();
                $table->string('status', 20)->default('active')->index();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('organization_user')) {
            Schema::create('organization_user', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->string('role', 20)->default('foreman');
                $table->boolean('is_active')->default(true);
                $table->timestamps();
                $table->unique(['organization_id', 'user_id']);
            });
        }

        if (! Schema::hasTable('invitations')) {
            Schema::create('invitations', function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
                $table->string('email')->nullable(); // пусто — ссылка-приглашение для мессенджера
                $table->string('role', 20);
                $table->timestamp('expires_at');
                $table->timestamp('accepted_at')->nullable();
                $table->foreignId('accepted_by')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['organization_id', 'accepted_at']);
            });
        }

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'is_platform_admin')) {
                $table->boolean('is_platform_admin')->default(false)->after('is_active');
            }
            if (! Schema::hasColumn('users', 'current_organization_id')) {
                $table->foreignId('current_organization_id')->nullable()->after('is_platform_admin')
                    ->constrained('organizations')->nullOnDelete();
            }
            if (! Schema::hasColumn('users', 'last_login_at')) {
                $table->timestamp('last_login_at')->nullable()->after('remember_token');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('current_organization_id');
            $table->dropColumn(['is_platform_admin', 'last_login_at']);
        });
        Schema::dropIfExists('invitations');
        Schema::dropIfExists('organization_user');
        Schema::dropIfExists('organizations');
    }
};
