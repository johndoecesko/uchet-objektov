<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Employee;
use App\Models\Expense;
use App\Models\Membership;
use App\Models\Organization;
use App\Models\Project;
use App\Models\User;
use App\Support\Demo\Demo;
use App\Support\Demo\DemoReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\SeedsAccounting;
use Tests\Feature\Concerns\UsesDemo;
use Tests\TestCase;

/** Пересоздание демо: строит демо, не трогает ничего чужого, откатывается при любом подозрении */
class DemoResetTest extends TestCase
{
    use RefreshDatabase, SeedsAccounting, UsesDemo;

    /** Все строки, не относящиеся к демо-организации: по всем таблицам с данными, учётки, организации */
    private function foreignSnapshot(): array
    {
        $demoId = Organization::where('is_demo', true)->value('id') ?? 0;
        $personaIds = User::where('is_demo', true)->pluck('id')->all() ?: [0];
        $orgTables = [...DemoReset::TABLES, 'organization_user'];
        $out = [];
        foreach ([...$orgTables, 'social_accounts', 'platform_counters'] as $t) {
            $q = DB::table($t);
            if (in_array($t, $orgTables, true)) {
                $q->where(fn ($w) => $w->where('organization_id', '<>', $demoId)->orWhereNull('organization_id'));
            }
            if ($t === 'activity_log') {
                // история персонажей (создание учёток) — не чужая
                $q->where(fn ($w) => $w->whereNotNull('organization_id')->orWhereNull('subject_id')->orWhereNotIn('subject_id', $personaIds));
            }
            $out[$t] = $q->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
        }
        $out['users'] = DB::table('users')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();
        $out['organizations'] = DB::table('organizations')->where('is_demo', false)->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();

        return $out;
    }

    private function demoProjectIds(): array
    {
        return DB::table('projects')->where('organization_id', $this->demo->id)->orderBy('id')->pluck('id')->all();
    }

    public function test_create_builds_demo_organization_and_three_personas(): void
    {
        config(['demo.enabled' => true]);
        $this->artisan('app:demo-reset --create')->assertSuccessful();

        $org = Organization::where('is_demo', true)->sole();
        $this->assertSame('demo', $org->slug);
        $this->assertNull($org->inn);
        $personas = User::where('is_demo', true)->get();
        $this->assertCount(3, $personas);
        foreach ($personas as $u) {
            $this->assertFalse($u->has_password);
            $this->assertFalse((bool) $u->is_platform_admin);
            $this->assertNotNull($u->email_verified_at);
            $this->assertNull($u->consent_at);
            $this->assertStringEndsWith('@finprorab.invalid', $u->email);
            $this->assertSame([$org->id], Membership::acrossOrganizations()->where('user_id', $u->id)->pluck('organization_id')->all());
        }
        $this->useOrganization($org);
        $this->assertSame(4, Project::count());
        $this->assertSame(5, Employee::count());
        $this->assertSame(User::where('email', DemoReset::PERSONAS['admin'][0])->value('id'), Demo::entryUser()->id);

        // второй раз — отказ
        $this->artisan('app:demo-reset --create')->assertFailed();
        $this->assertSame(1, Organization::where('is_demo', true)->count());
    }

    public function test_create_refuses_when_slug_is_taken_by_real_organization(): void
    {
        DB::table('organizations')->insert(['name' => 'Демо (настоящая)', 'slug' => 'demo', 'status' => 'active', 'created_at' => now(), 'updated_at' => now()]);
        $this->artisan('app:demo-reset --create')->assertFailed();
        $this->assertSame(0, Organization::where('is_demo', true)->count());
        $this->assertSame(0, User::where('is_demo', true)->count());
    }

    public function test_reset_does_not_touch_other_organizations(): void
    {
        Storage::fake('local');
        $this->fillData();
        Storage::disk('local')->put('receipts/'.$this->org->id.'/real.jpg', 'x');
        $this->makeDemo();
        DB::table('sessions')->insert([
            ['id' => 'real-session', 'user_id' => $this->admin->id, 'payload' => 'a', 'last_activity' => now()->getTimestamp()],
            ['id' => 'demo-session', 'user_id' => $this->demoAdmin->id, 'payload' => 'b', 'last_activity' => now()->getTimestamp()],
        ]);

        $before = $this->foreignSnapshot();
        $sessions = DB::table('sessions')->orderBy('id')->get()->all();
        $demoUser = DB::table('users')->where('id', $this->demoAdmin->id)->first();

        $this->artisan('app:demo-reset')->assertSuccessful();
        $this->artisan('app:demo-reset')->assertSuccessful();

        $this->assertEquals($before, $this->foreignSnapshot());
        $this->assertEquals($sessions, DB::table('sessions')->orderBy('id')->get()->all(), 'сессии гостей не рвутся');
        $this->assertEquals($demoUser, DB::table('users')->where('id', $this->demoAdmin->id)->first(), 'строка демо-пользователя не меняется');
        Storage::disk('local')->assertExists('receipts/'.$this->org->id.'/real.jpg');

        $this->useOrganization($this->demo);
        $this->assertSame(4, Project::count(), 'данные демо не удвоились');
    }

    public function test_dates_are_fresh_after_time_passes(): void
    {
        $this->makeDemo();
        $this->travel(10)->days();
        $this->artisan('app:demo-reset')->assertSuccessful();

        $this->useOrganization($this->demo);
        // самый свежий расход в демо — топливо по школе 15 дней назад
        $this->assertSame(now()->startOfDay()->subDays(15)->toDateString(), Carbon::parse(Expense::max('date'))->toDateString());
    }

    public function test_reset_refuses_when_real_user_is_member_of_demo(): void
    {
        $this->makeDemo();
        DB::table('organization_user')->insert(['organization_id' => $this->demo->id, 'user_id' => $this->admin->id, 'role' => 'admin', 'is_active' => true, 'created_at' => now(), 'updated_at' => now()]);
        $projects = $this->demoProjectIds();

        $this->artisan('app:demo-reset')->assertFailed();
        $this->assertSame($projects, $this->demoProjectIds(), 'ничего не удалено');
    }

    public function test_reset_rolls_back_when_foreign_rows_change(): void
    {
        $this->makeDemo();
        $realOrg = $this->org->id;
        // «ошибка в коде заполнения»: при создании объекта демо кто-то пишет в чужую организацию
        Project::created(function (Project $p) use ($realOrg) {
            if (Demo::isDemoOrganizationId($p->organization_id)) {
                DB::table('settings')->insertOrIgnore(['organization_id' => $realOrg, 'key' => 'leak', 'value' => '1', 'created_at' => now(), 'updated_at' => now()]);
            }
        });
        $projects = $this->demoProjectIds();

        $this->artisan('app:demo-reset')->assertFailed();
        $this->assertSame(0, DB::table('settings')->where('key', 'leak')->count(), 'чужая запись откачена');
        $this->assertSame($projects, $this->demoProjectIds(), 'демо осталось как было');
    }

    public function test_only_demo_history_is_deleted(): void
    {
        $this->actingAs($this->admin);
        $this->fillData();
        auth()->forgetUser();
        $realHistory = Activity::acrossOrganizations()->where('organization_id', $this->org->id)->count();
        $this->assertGreaterThan(0, $realHistory);
        $this->makeDemo();
        $this->artisan('app:demo-reset')->assertSuccessful();

        $this->assertSame($realHistory, Activity::acrossOrganizations()->where('organization_id', $this->org->id)->count());
        $demoHistory = Activity::acrossOrganizations()->where('organization_id', $this->demo->id)->count();
        $this->assertGreaterThan(0, $demoHistory, 'история в демо есть — её видно в разделе «История»');
        $this->artisan('app:demo-reset')->assertSuccessful();
        $this->assertSame($demoHistory, Activity::acrossOrganizations()->where('organization_id', $this->demo->id)->count(), 'история демо не копится');
        $this->assertSame(0, Activity::acrossOrganizations()->whereNull('organization_id')->where('subject_type', (new User)->getMorphClass())
            ->whereIn('subject_id', User::where('is_demo', true)->pluck('id'))->count());
    }

    public function test_destroy_removes_only_demo(): void
    {
        $this->fillData();
        $this->makeDemo();
        $before = $this->foreignSnapshot();

        $this->artisan('app:demo-reset --destroy')->assertFailed();
        $this->assertSame(1, Organization::where('is_demo', true)->count(), 'без --force не удаляет');

        $this->artisan('app:demo-reset --destroy --force')->assertSuccessful();
        $this->assertSame(0, Organization::where('is_demo', true)->count());
        $this->assertSame(0, User::where('is_demo', true)->count());
        $before['users'] = array_values(array_filter($before['users'], fn ($u) => ! $u['is_demo']));
        $this->assertEquals($before, $this->foreignSnapshot());
    }

    public function test_reset_skipped_when_demo_disabled(): void
    {
        $this->makeDemo();
        $ids = $this->demoProjectIds();
        config(['demo.enabled' => false]);

        $this->artisan('app:demo-reset')->assertSuccessful()->expectsOutputToContain('пропущено');
        $this->assertSame($ids, $this->demoProjectIds());

        $this->artisan('app:demo-reset --force')->assertSuccessful();
        $this->assertNotSame($ids, $this->demoProjectIds());
    }
}
