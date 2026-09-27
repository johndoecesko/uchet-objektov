<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('role', 20)->default('foreman')->after('email');
            $table->boolean('is_active')->default(true)->after('role');
            $table->string('phone', 50)->nullable()->after('is_active');
        });

        Schema::create('expense_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('is_labor')->default(false); // сюда попадает начисленная зарплата из журнала работ
            $table->unsignedInteger('sort')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('customer')->nullable();
            $table->string('address')->nullable();
            $table->string('contract_number')->nullable();
            $table->date('contract_date')->nullable();
            $table->decimal('contract_amount', 14, 2)->default(0);
            $table->string('status', 20)->default('active');
            $table->date('start_date')->nullable();
            $table->date('end_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->index('status');
        });

        Schema::create('project_budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2)->default(0);
            $table->timestamps();
            $table->unique(['project_id', 'expense_category_id']);
        });

        Schema::create('employees', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('phone', 50)->nullable();
            $table->string('position')->nullable();
            $table->decimal('day_rate', 12, 2)->default(0);
            $table->unsignedTinyInteger('card_percent')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('expenses', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('expense_category_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('supplier')->nullable();
            $table->string('document')->nullable(); // № чека / счёта / УПД
            $table->string('payment_source', 20)->default('bank');
            $table->foreignId('employee_id')->nullable()->constrained()->restrictOnDelete(); // чей подотчёт
            $table->text('description')->nullable();
            $table->json('attachments')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['project_id', 'date']);
        });

        Schema::create('work_logs', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->string('work')->nullable();
            $table->decimal('days', 5, 2)->default(1);
            $table->decimal('rate', 12, 2)->default(0);
            $table->decimal('amount', 14, 2)->default(0);
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['project_id', 'date']);
            $table->index(['employee_id', 'date']);
        });

        Schema::create('payouts', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('project_id')->nullable()->constrained()->nullOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('method', 20)->default('card');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['employee_id', 'date']);
        });

        Schema::create('advances', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->string('type', 20)->default('issue');
            $table->decimal('amount', 14, 2);
            $table->string('method', 20)->default('cash');
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['employee_id', 'date']);
        });

        Schema::create('incomes', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->foreignId('project_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 14, 2);
            $table->string('type', 20)->default('advance');
            $table->string('method', 20)->default('bank');
            $table->string('document')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['project_id', 'date']);
        });
    }

    public function down(): void
    {
        foreach (['incomes', 'advances', 'payouts', 'work_logs', 'expenses', 'employees', 'project_budgets', 'projects', 'expense_categories'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['role', 'is_active', 'phone']);
        });
    }
};
