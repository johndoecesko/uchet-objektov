<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Перенос рабочих данных в организацию №1: база в схеме до организаций (как на проде 2026-10-24)
 * → migrate → всё принадлежит организации №1, роли перенесены, настройки на месте, повторный запуск безопасен.
 */
class MigrationToOrganizationsTest extends TestCase
{
    private string $db;

    protected function setUp(): void
    {
        parent::setUp();
        $this->db = tempnam(sys_get_temp_dir(), 'mig').'.sqlite';
        touch($this->db);
        config(['database.connections.mig' => ['driver' => 'sqlite', 'database' => $this->db, 'prefix' => '', 'foreign_key_constraints' => true]]);
        DB::setDefaultConnection('mig');
    }

    protected function tearDown(): void
    {
        DB::disconnect('mig');
        @unlink($this->db);
        parent::tearDown();
    }

    private function migrate(bool $onlyOld): void
    {
        $files = glob(database_path('migrations/*.php'));
        if ($onlyOld) {
            $files = array_filter($files, fn ($f) => basename($f) < '2026_10_24');
        }
        foreach ($files as $f) {
            Artisan::call('migrate', ['--database' => 'mig', '--path' => $f, '--realpath' => true, '--force' => true]);
        }
    }

    public function test_existing_data_moves_to_first_organization(): void
    {
        $this->migrate(onlyOld: true);
        $this->assertFalse(Schema::hasTable('organizations'));

        $now = now();
        DB::table('users')->insert([
            ['id' => 1, 'name' => 'Александр', 'email' => 'a@x.ru', 'password' => 'x', 'role' => 'admin', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
            ['id' => 2, 'name' => 'Инженер', 'email' => 'f@x.ru', 'password' => 'x', 'role' => 'foreman', 'is_active' => true, 'created_at' => $now, 'updated_at' => $now],
        ]);
        $mat = DB::table('expense_categories')->insertGetId(['name' => 'Материалы', 'sort' => 10, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('projects')->insert(['id' => 1, 'name' => 'Объект', 'contract_amount' => 100, 'status' => 'active', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('employees')->insert(['id' => 1, 'name' => 'Сотрудник', 'user_id' => 2, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('expenses')->insert(['date' => '2026-10-01', 'project_id' => 1, 'expense_category_id' => $mat, 'amount' => 50, 'created_by' => 1, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('expenses')->insert(['date' => '2026-10-02', 'project_id' => 1, 'expense_category_id' => $mat, 'amount' => 70, 'deleted_at' => $now, 'created_at' => $now, 'updated_at' => $now]);
        DB::table('settings')->insert(['key' => 'attention.advance_days', 'value' => '21', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('activity_log')->insert(['description' => 'created', 'subject_type' => 'App\Models\Project', 'subject_id' => 1, 'created_at' => $now, 'updated_at' => $now]);

        $this->migrate(onlyOld: false);

        $org = DB::table('organizations')->first();
        $this->assertSame('Моя организация', $org->name);
        $this->assertSame('moya-organizatsiya', $org->slug);
        $this->assertSame(1, DB::table('organizations')->count());

        foreach (['projects', 'employees', 'expenses', 'expense_categories', 'activity_log'] as $t) {
            $this->assertSame(0, DB::table($t)->whereNull('organization_id')->count(), $t);
            $this->assertSame(0, DB::table($t)->where('organization_id', '!=', $org->id)->count(), $t);
        }
        $this->assertSame(2, DB::table('expenses')->count(), 'и удалённые в корзину тоже');
        $this->assertSame(['admin', 'foreman'], DB::table('organization_user')->orderBy('user_id')->pluck('role')->all());
        $this->assertSame([$org->id, $org->id], DB::table('users')->orderBy('id')->pluck('current_organization_id')->map(fn ($v) => (int) $v)->all());
        $this->assertSame('21', (string) DB::table('settings')->where('organization_id', $org->id)->where('key', 'attention.advance_days')->value('value'));
        $this->assertFalse(Schema::hasTable('settings_old'));

        // повторный запуск ничего не ломает и не плодит организаций
        $this->migrate(onlyOld: false);
        $this->assertSame(1, DB::table('organizations')->count());
        $this->assertSame(2, DB::table('organization_user')->count());
    }

    public function test_fresh_install_has_no_organizations(): void
    {
        $this->migrate(onlyOld: false);
        $this->assertSame(0, DB::table('organizations')->count());
        $this->assertSame(0, DB::table('expense_categories')->count());
    }
}
