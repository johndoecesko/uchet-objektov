<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Auth\Login;
use App\Filament\Auth\Register;
use App\Filament\Auth\RequestPasswordReset;
use App\Filament\Resources\MemberResource\Pages\ListMembers;
use App\Models\Invitation;
use App\Models\Membership;
use App\Models\SocialAccount;
use App\Models\User;
use App\Support\CurrentOrganization;
use App\Support\Demo\Demo;
use App\Support\Demo\DemoReset;
use App\Support\Max\MaxBot;
use App\Support\OrganizationMismatch;
use App\Support\PlatformCounter;
use App\Support\Registration;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Feature\Concerns\SeedsAccounting;
use Tests\Feature\Concerns\UsesDemo;
use Tests\TestCase;

/** Вход в демо: только в персонажа демо, только в демо-организацию, ни одной дороги в настоящие учётки */
class DemoAccessTest extends TestCase
{
    use RefreshDatabase, UsesDemo;
    use SeedsAccounting { setUp as seedAccounting; }

    protected function setUp(): void
    {
        $this->seedAccounting();
        auth()->forgetUser();
    }

    public function test_disabled_demo_has_no_button_and_404(): void
    {
        $this->makeDemo();
        config(['demo.enabled' => false]);

        $this->get('/')->assertOk()->assertDontSee('Посмотреть демо');
        $this->get('/demo')->assertNotFound();
        $this->post('/demo')->assertNotFound();
        $this->assertGuest();
    }

    public function test_button_on_landing_logs_into_demo_persona(): void
    {
        $this->makeDemo();
        $this->get('/')->assertOk()->assertSee('Посмотреть демо')->assertSee(route('demo.enter'), false);

        $this->post('/demo')->assertRedirect('/app/demo');
        $this->assertAuthenticatedAs($this->demoAdmin);
        $this->get('/app/demo')->assertOk()->assertSee('Это демо')->assertSee('Выйти из демо')
            ->assertHeader('X-Robots-Tag', 'noindex, nofollow');
        $this->assertSame(1, PlatformCounter::sum('demo.enter', 1));
        $this->assertNull($this->demoAdmin->fresh()->last_login_at, 'вход в демо не пишет в учётку');
    }

    public function test_demo_ignores_anything_the_guest_sends(): void
    {
        $this->makeDemo();
        $this->post('/demo', ['email' => $this->admin->email, 'user_id' => $this->admin->id, 'password' => 'secret123', 'organization' => 'montazh'])
            ->assertRedirect('/app/demo');
        $this->assertAuthenticatedAs($this->demoAdmin);
        $this->get('/app/montazh')->assertNotFound();
    }

    /** Любое нарушение инварианта — 503, гость остаётся гостем */
    public function test_broken_invariants_refuse_entry(): void
    {
        $this->makeDemo();
        $breaks = [
            'членство в настоящей организации' => fn () => DB::table('organization_user')->insert(['organization_id' => $this->org->id, 'user_id' => $this->demoAdmin->id, 'role' => 'admin', 'is_active' => true]),
            'два кандидата' => fn () => DB::table('organization_user')->where('user_id', User::where('email', DemoReset::PERSONAS['manager'][0])->value('id'))->update(['role' => 'admin']),
            'админ платформы' => fn () => DB::table('users')->where('id', $this->demoAdmin->id)->update(['is_platform_admin' => true]),
            'учётка отключена' => fn () => DB::table('users')->where('id', $this->demoAdmin->id)->update(['is_active' => false]),
            'настоящий участник в демо' => fn () => DB::table('organization_user')->insert(['organization_id' => $this->demo->id, 'user_id' => $this->foreman->id, 'role' => 'foreman', 'is_active' => true]),
            'вторая демо-организация' => fn () => DB::table('organizations')->insert(['name' => 'Ещё демо', 'slug' => 'demo-2', 'status' => 'active', 'is_demo' => true]),
        ];

        foreach ($breaks as $what => $break) {
            Cache::flush(); // лимит частоты входа — не про этот тест
            DB::beginTransaction();
            try {
                $break();
                $this->post('/demo')->assertStatus(503);
                $this->assertGuest();
            } finally {
                DB::rollBack();
            }
            $this->assertNotNull(Demo::entryUser(), "после отката «{$what}» вход снова работает");
        }
    }

    public function test_logged_in_user_is_not_switched_to_demo(): void
    {
        $this->makeDemo();
        $this->actingAs($this->admin);
        $this->post('/demo')->assertRedirect('/app');
        $this->assertAuthenticatedAs($this->admin);
        $this->get('/demo')->assertRedirect('/app');
    }

    public function test_entry_is_rate_limited(): void
    {
        $this->makeDemo();
        for ($i = 1; $i <= 5; $i++) {
            $this->post('/demo')->assertRedirect();
        }
        $this->post('/demo')->assertStatus(429);
    }

    public function test_demo_persona_cannot_log_in_with_password_or_reset_it(): void
    {
        $this->makeDemo();
        DB::table('users')->where('id', $this->demoAdmin->id)->update(['password' => Hash::make('known-pass-1')]);

        Livewire::test(Login::class)
            ->fillForm(['email' => DemoReset::PERSONAS['admin'][0], 'password' => 'known-pass-1'])
            ->call('authenticate')
            ->assertHasFormErrors(['email']);
        $this->assertGuest();

        Notification::fake();
        Livewire::test(RequestPasswordReset::class)->fillForm(['email' => DemoReset::PERSONAS['admin'][0]])->call('request')
            ->assertNotified();
        Notification::assertNothingSent();
        $this->assertSame(0, DB::table('password_reset_tokens')->count());
    }

    public function test_platform_and_foreign_organization_are_closed(): void
    {
        $this->makeDemo();
        $this->enterDemo();
        $this->get('/platform')->assertForbidden();
        $this->get('/app/montazh')->assertNotFound();
        $this->get('/app/montazh/projects/'.$this->project->id)->assertNotFound();
    }

    public function test_real_user_cannot_get_into_demo_organization(): void
    {
        $this->makeDemo();
        $this->assertThrows(fn () => $this->demo->addMember($this->admin, UserRole::Admin));
        // даже если строку вписать в обход моделей — панель не пустит
        DB::table('organization_user')->insert(['organization_id' => $this->demo->id, 'user_id' => $this->admin->id, 'role' => 'admin', 'is_active' => true]);
        $this->actingAs($this->admin->forgetMemberships())->get('/app/demo')->assertNotFound();
        DB::table('organization_user')->where('organization_id', $this->demo->id)->where('user_id', $this->admin->id)->delete();

        // приглашение в демо (созданное в обход) не работает
        $invite = Demo::allowWrites(fn () => app(CurrentOrganization::class)->run($this->demo, fn () => Invitation::create(['role' => UserRole::Admin])));
        auth()->forgetUser();
        $this->get($invite->url())->assertOk()->assertSee('Приглашение недействительно');
        $this->actingAs($this->admin)->get($invite->url())->assertSee('Приглашение недействительно');
        session([Registration::SESSION_KEY => $invite->id]);
        $this->post(route('invite.accept', $invite->id))->assertForbidden();
        $this->assertNull($this->admin->forgetMemberships()->roleIn($this->demo));
    }

    public function test_demo_cannot_accept_invitation_into_real_organization(): void
    {
        $this->makeDemo();
        $this->actingAs($this->admin);
        $this->useOrganization($this->org);
        Livewire::test(ListMembers::class)->callAction('inviteLink', data: ['role' => UserRole::Admin->value]);
        $invite = Invitation::acrossOrganizations()->where('organization_id', $this->org->id)->firstOrFail();
        auth()->forgetUser();

        $this->enterDemo();
        $this->get($invite->url())->assertRedirect('/app/demo');
        session([Registration::SESSION_KEY => $invite->id]);
        $this->post(route('invite.accept', $invite->id))->assertForbidden();
        $this->assertNull($this->demoAdmin->forgetMemberships()->roleIn($this->org));
        $this->assertTrue($invite->fresh()->isPending());
        // и в обход приглашений: из консоли (без вошедшего) персонажа демо в настоящую организацию не добавить
        $this->post('/demo/exit');
        auth()->forgetUser();
        $this->assertThrows(fn () => $this->org->addMember($this->demoAdmin, UserRole::Admin), OrganizationMismatch::class);
    }

    public function test_social_linking_is_closed(): void
    {
        config(['services.yandex.client_id' => 'ya-id', 'services.yandex.client_secret' => 'ya-secret']);
        $this->makeDemo();
        $this->enterDemo();

        $this->get('/auth/yandex/redirect?link=1')->assertRedirect('/app/demo');
        $this->assertNull(session('social.flow'));
        $this->assertSame(0, SocialAccount::count());

        // и вход через Яндекс с почтой персонажа не впускает в демо-учётку
        $this->post('/demo/exit')->assertRedirect('/');
        $this->assertGuest();
        $this->get('/auth/yandex/redirect')->assertRedirect();
        $state = session('social.flow')['state'];
        Http::fake([
            'oauth.yandex.ru/token' => Http::response(['access_token' => 'ya-token', 'token_type' => 'bearer']),
            'login.yandex.ru/info*' => Http::response(['id' => 'ya-1', 'login' => 'x', 'default_email' => DemoReset::PERSONAS['admin'][0], 'real_name' => 'Гость']),
        ]);
        $this->get('/auth/yandex/callback?code=c&state='.$state);
        $this->assertGuest();
        $this->assertSame(0, SocialAccount::where('user_id', $this->demoAdmin->id)->count());
    }

    public function test_max_cannot_be_linked_to_demo(): void
    {
        config(['services.max.token' => 'bot-token']);
        $this->makeDemo();
        $this->assertThrows(fn () => MaxBot::linkCode($this->demoAdmin));

        // код, каким-то образом выданный демо-учётке, бот не принимает
        Http::fake(['*' => Http::response(['success' => true])]);
        config(['services.max.webhook_secret' => 's', 'services.max.url' => 'https://platform-api2.max.ru']);
        Cache::put('max:link:ABCDEF', $this->demoAdmin->id, 600);
        $this->postJson('/api/max/webhook', ['update_type' => 'message_created', 'message' => [
            'sender' => ['user_id' => 777], 'recipient' => ['chat_type' => 'dialog'], 'body' => ['text' => 'ABCDEF'],
        ]], ['X-Max-Bot-Api-Secret' => 's'])->assertOk();
        $this->assertNull($this->demoAdmin->fresh()->max_user_id);

        $this->enterDemo();
        $this->get('/app/demo')->assertDontSee('Подключить MAX');
    }

    public function test_register_from_demo_logs_out_and_marks_source(): void
    {
        config(['auth.registration_enabled' => true]);
        $this->makeDemo();
        $this->enterDemo();

        $this->post('/demo/register')->assertRedirect('/app/register');
        $this->assertGuest();
        $this->assertSame(1, PlatformCounter::sum('demo.signup_click', 1));

        $this->get('/app/register')->assertOk();
        Livewire::test(Register::class)
            ->fillForm(['name' => 'Из демо', 'email' => 'from-demo@test.ru', 'password' => 'Str0ng-pass!', 'passwordConfirmation' => 'Str0ng-pass!', 'terms' => true, 'consent' => true])
            ->call('register')->assertHasNoFormErrors();
        $user = User::where('email', 'from-demo@test.ru')->firstOrFail();
        $this->assertSame('demo', $user->signup_source);
        $this->assertFalse((bool) $user->is_demo);
        $this->assertSame(1, PlatformCounter::sum('demo.registered', 1));
    }

    public function test_register_button_hidden_when_registration_closed(): void
    {
        $this->makeDemo();
        $this->enterDemo();
        $this->get('/app/demo')->assertOk()->assertSee('Регистрация по приглашению')->assertDontSee(route('demo.register'), false);
        $this->post('/demo/exit')->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_noindex_and_robots(): void
    {
        $this->makeDemo();
        $this->get('/demo')->assertOk()->assertHeader('X-Robots-Tag', 'noindex, nofollow')->assertSee('noindex', false);
        $this->get('/robots.txt')->assertSee('Disallow: /demo');
    }

    public function test_disabling_demo_logs_out_demo_sessions(): void
    {
        $this->makeDemo();
        $this->enterDemo();
        config(['demo.enabled' => false]);

        $this->get('/app/demo')->assertRedirect('/');
        $this->assertGuest();
        $this->assertFalse($this->demoAdmin->canAccessPanel(Filament::getPanel('admin')));
    }

    public function test_prune_removes_only_idle_demo_sessions(): void
    {
        config(['session.driver' => 'database']);
        $this->makeDemo();
        DB::table('sessions')->insert([
            ['id' => 'demo-old', 'user_id' => $this->demoAdmin->id, 'payload' => '', 'last_activity' => now()->subHours(4)->getTimestamp()],
            ['id' => 'demo-new', 'user_id' => $this->demoAdmin->id, 'payload' => '', 'last_activity' => now()->subHour()->getTimestamp()],
            ['id' => 'real-old', 'user_id' => $this->admin->id, 'payload' => '', 'last_activity' => now()->subDays(3)->getTimestamp()],
        ]);
        $this->assertSame(1, Demo::pruneSessions());
        $this->assertSame(['demo-new', 'real-old'], DB::table('sessions')->orderBy('id')->pluck('id')->all());
        $this->assertSame(0, Membership::acrossOrganizations()->where('organization_id', $this->org->id)->whereIn('user_id', User::where('is_demo', true)->pluck('id'))->count());
    }
}
