<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/*
 * organization_id во всех данных организации и перенос существующих данных в организацию №1.
 * Идемпотентна: каждый шаг проверяет, сделан ли он; повторный запуск после сбоя доделывает остальное.
 * Организация №1 создаётся, только если в базе уже есть пользователи или данные (на чистой установке — нет).
 * Её название потом меняется в профиле организации (или командой app:organization).
 */
return new class extends Migration
{
    /** Таблицы данных организации: organization_id обязателен */
    private array $tables = [
        'projects', 'project_budgets', 'employees', 'expenses', 'expense_categories',
        'work_logs', 'payouts', 'advances', 'incomes',
    ];

    public function up(): void
    {
        foreach ([...$this->tables, 'activity_log'] as $name) {
            if (! Schema::hasColumn($name, 'organization_id')) {
                Schema::table($name, function (Blueprint $table) use ($name) {
                    $fk = $table->foreignId('organization_id')->nullable()->after('id')->constrained();
                    $name === 'activity_log' ? $fk->cascadeOnDelete() : $fk->restrictOnDelete();
                });
            }
        }

        $orgId = $this->firstOrganization();

        if ($orgId) {
            foreach ([...$this->tables, 'activity_log'] as $name) {
                DB::table($name)->whereNull('organization_id')->update(['organization_id' => $orgId]);
            }

            // Все существующие пользователи — участники организации №1 с прежней ролью
            $hasRole = Schema::hasColumn('users', 'role');
            foreach (DB::table('users')->get() as $u) {
                $exists = DB::table('organization_user')->where('user_id', $u->id)->exists();
                if (! $exists) {
                    DB::table('organization_user')->insert([
                        'organization_id' => $orgId,
                        'user_id' => $u->id,
                        'role' => $hasRole && in_array($u->role, ['admin', 'manager', 'foreman'], true) ? $u->role : 'foreman',
                        'is_active' => true,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }
            DB::table('users')->whereNull('current_organization_id')->update(['current_organization_id' => $orgId]);
        }

        $this->rebuildSettings($orgId);

        // Проверка: ни одной строки данных без организации
        foreach ($this->tables as $name) {
            $nulls = DB::table($name)->whereNull('organization_id')->count();
            if ($nulls > 0) {
                throw new RuntimeException("В таблице {$name} осталось {$nulls} строк без organization_id — перенос не завершён");
            }
        }

        foreach ($this->tables as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->unsignedBigInteger('organization_id')->nullable(false)->change();
            });
        }
    }

    /** id организации №1: уже созданной или новой, если есть что переносить */
    private function firstOrganization(): ?int
    {
        $existing = DB::table('organizations')->orderBy('id')->value('id');
        if ($existing) {
            return $existing;
        }

        $hasData = DB::table('users')->exists();
        foreach ($this->tables as $name) {
            $hasData = $hasData || DB::table($name)->exists();
        }
        if (! $hasData) {
            return null;
        }

        return DB::table('organizations')->insertGetId([
            'name' => 'Моя организация',
            'slug' => 'moya-organizatsiya',
            'status' => 'active',
            'created_by' => DB::table('users')->orderBy('id')->value('id'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /** settings: ключ был первичным ключом таблицы — теперь (organization_id, key) */
    private function rebuildSettings(?int $orgId): void
    {
        if (Schema::hasColumn('settings', 'organization_id')) {
            Schema::dropIfExists('settings_old');

            return;
        }

        if (! Schema::hasTable('settings_old')) {
            Schema::rename('settings', 'settings_old');
        } else {
            Schema::dropIfExists('settings');
        }

        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('key', 100);
            $table->json('value')->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'key']);
        });

        if ($orgId) {
            foreach (DB::table('settings_old')->get() as $s) {
                DB::table('settings')->insert([
                    'organization_id' => $orgId,
                    'key' => $s->key,
                    'value' => $s->value,
                    'created_at' => $s->created_at,
                    'updated_at' => $s->updated_at,
                ]);
            }
        }

        Schema::drop('settings_old');
    }

    public function down(): void
    {
        foreach ([...$this->tables, 'activity_log'] as $name) {
            Schema::table($name, fn (Blueprint $table) => $table->dropConstrainedForeignId('organization_id'));
        }

        $rows = DB::table('settings')->orderBy('organization_id')->get()->unique('key');
        Schema::drop('settings');
        Schema::create('settings', function (Blueprint $table) {
            $table->string('key', 100)->primary();
            $table->json('value')->nullable();
            $table->timestamps();
        });
        foreach ($rows as $s) {
            DB::table('settings')->insert(['key' => $s->key, 'value' => $s->value, 'created_at' => $s->created_at, 'updated_at' => $s->updated_at]);
        }
    }
};
