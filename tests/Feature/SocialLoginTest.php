<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Auth\EditProfile;
use App\Models\SocialAccount;
use App\Models\User;
use App\Notifications\Auth\VerifyEmail;
use App\Support\Social\Provider;
use Filament\Actions\Testing\TestAction;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Feature\Concerns\SeedsAccounting;
use Tests\TestCase;

/** Вход через Яндекс ID и VK ID: кнопки, state/PKCE, вход, привязка по почте, новая учётка с согласием, привязка из профиля */
class SocialLoginTest extends TestCase
{
    use RefreshDatabase, SeedsAccounting {
        SeedsAccounting::setUp as seedAccounting;
    }

    protected function setUp(): void
    {
        $this->seedAccounting();
        $this->useOrganization(null);
        config([
            'services.yandex.client_id' => 'ya-id', 'services.yandex.client_secret' => 'ya-secret',
            'services.vkid.client_id' => 'vk-id',
            'auth.registration_enabled' => true,
        ]);
    }

    /** Ответы Яндекса: токен и данные пользователя */
    private function fakeYandex(string $id = 'ya-777', ?string $email = 'ivan@yandex.ru', string $name = 'Иван Яндексов'): void
    {
        Http::fake([
            'oauth.yandex.ru/token' => Http::response(['access_token' => 'ya-token', 'token_type' => 'bearer']),
            'login.yandex.ru/info*' => Http::response(array_filter(['id' => $id, 'login' => 'ivan', 'default_email' => $email, 'real_name' => $name])),
        ]);
    }

    /** Пройти редирект на провайдера и вернуться с кодом; возвращает ответ callback */
    private function roundTrip(string $provider, array $extra = [])
    {
        $go = $this->get('/auth/'.$provider.'/redirect'.($extra['link'] ?? false ? '?link=1' : ''));
        $go->assertRedirect();
        parse_str(parse_url($go->headers->get('Location'), PHP_URL_QUERY), $q);

        return $this->get('/auth/'.$provider.'/callback?'.http_build_query(['code' => 'the-code', 'state' => $extra['state'] ?? $q['state'], 'device_id' => 'dev-1']));
    }

    public function test_buttons_only_with_keys(): void
    {
        $this->useOrganization(null);
        $this->get('/app/login')->assertSee('Войти через Яндекс ID')->assertSee('Войти через VK ID');
        $this->get('/app/register')->assertSee('Зарегистрироваться через Яндекс ID');

        config(['services.yandex.client_id' => null, 'services.vkid.client_id' => null]);
        $this->get('/app/login')->assertDontSee('через Яндекс ID')->assertDontSee('через VK ID');
        $this->get('/auth/yandex/redirect')->assertNotFound();
    }

    public function test_redirect_uses_pkce_and_state(): void
    {
        foreach (['yandex' => 'https://oauth.yandex.ru/authorize?', 'vkid' => 'https://id.vk.ru/authorize?'] as $provider => $base) {
            $go = $this->get('/auth/'.$provider.'/redirect');
            $url = $go->headers->get('Location');
            $this->assertStringStartsWith($base, $url);
            parse_str(parse_url($url, PHP_URL_QUERY), $q);
            $flow = session('social.flow');
            $this->assertSame($flow['state'], $q['state']);
            $this->assertSame(Provider::codeChallenge($flow['verifier']), $q['code_challenge']);
            $this->assertSame('S256', $q['code_challenge_method']);
            $this->assertSame(url('/auth/'.$provider.'/callback'), $q['redirect_uri']);
            $this->assertArrayNotHasKey('code_verifier', $q);
        }
    }

    public function test_wrong_state_is_rejected(): void
    {
        $this->fakeYandex();
        $this->roundTrip('yandex', ['state' => 'forged'])->assertRedirect('/app/login');
        $this->assertGuest();
        Http::assertNothingSent();
    }

    public function test_new_yandex_user_confirms_consent_and_gets_verified_account(): void
    {
        $this->fakeYandex();
        $this->roundTrip('yandex')->assertRedirect(route('social.complete'));
        $this->assertGuest();

        $this->get('/auth/complete')->assertOk()->assertSee('Иван Яндексов', false)->assertSee('ivan@yandex.ru')
            ->assertSee(route('consent'), false);

        $this->post('/auth/complete', ['name' => 'Иван'])->assertSessionHasErrors(['terms', 'consent']);
        $this->assertSame(2, User::count(), 'без согласия учётка не создаётся');

        $this->post('/auth/complete', ['name' => 'Иван', 'terms' => '1', 'consent' => '1'])->assertRedirect('/app');
        $user = User::where('email', 'ivan@yandex.ru')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->email_verified_at, 'почта от провайдера подтверждена им');
        $this->assertFalse($user->has_password);
        $this->assertSame(config('legal.version'), $user->consent_version);
        $this->assertSame(['yandex', 'ya-777', 'ivan@yandex.ru'], [$user->socialAccounts[0]->provider, $user->socialAccounts[0]->provider_user_id, $user->socialAccounts[0]->email]);

        Http::assertSent(fn (Request $r) => $r->url() === 'https://oauth.yandex.ru/token'
            && $r['client_secret'] === 'ya-secret' && $r['code'] === 'the-code' && strlen($r['code_verifier']) === 64);
        Http::assertSent(fn (Request $r) => str_starts_with($r->url(), 'https://login.yandex.ru/info') && $r->header('Authorization') === ['OAuth ya-token']);
    }

    public function test_linked_account_logs_in_directly(): void
    {
        SocialAccount::create(['user_id' => $this->foreman->id, 'provider' => 'yandex', 'provider_user_id' => 'ya-777']);
        $this->fakeYandex(email: 'other@yandex.ru');

        $this->roundTrip('yandex')->assertRedirect('/app');
        $this->assertAuthenticatedAs($this->foreman);
    }

    public function test_verified_provider_email_links_existing_account(): void
    {
        $this->fakeYandex(email: 'F@test.ru');

        $this->roundTrip('yandex')->assertRedirect('/app');
        $this->assertAuthenticatedAs($this->foreman);
        $this->assertSame('ya-777', $this->foreman->socialAccounts()->value('provider_user_id'));
    }

    public function test_unverified_or_missing_email_does_not_link_existing_account(): void
    {
        // VK ID без почты: к учётке f@test.ru не привязываем, предлагаем новую — а почту f@test.ru занять нельзя
        Http::fake([
            'id.vk.ru/oauth2/auth' => Http::response(['access_token' => 'vk-token', 'user_id' => 555]),
            'id.vk.ru/oauth2/user_info' => Http::response(['user' => ['user_id' => '555', 'first_name' => 'Пётр', 'last_name' => 'Вконтактов']]),
        ]);
        $this->roundTrip('vkid')->assertRedirect(route('social.complete'));
        $this->assertGuest();
        $this->assertSame(0, SocialAccount::count());

        $this->get('/auth/complete')->assertOk()->assertSee('не передал почту');
        $this->post('/auth/complete', ['name' => 'Пётр', 'email' => 'f@test.ru', 'terms' => '1', 'consent' => '1'])
            ->assertSessionHasErrors(['email']);
        $this->assertGuest();

        Http::assertSent(fn (Request $r) => $r->url() === 'https://id.vk.ru/oauth2/auth'
            && $r['device_id'] === 'dev-1' && $r['client_id'] === 'vk-id' && strlen($r['code_verifier']) === 64 && ! isset($r['service_token']));
    }

    public function test_vk_user_without_email_enters_it_and_confirms_by_letter(): void
    {
        Notification::fake();
        Http::fake([
            'id.vk.ru/oauth2/auth' => Http::response(['access_token' => 'vk-token', 'user_id' => 555]),
            'id.vk.ru/oauth2/user_info' => Http::response(['user' => ['user_id' => '555', 'first_name' => 'Пётр', 'last_name' => 'Вконтактов']]),
        ]);
        $this->roundTrip('vkid');
        $this->post('/auth/complete', ['name' => 'Пётр', 'email' => 'Petr@Test.ru', 'terms' => '1', 'consent' => '1'])->assertRedirect('/app');

        $user = User::where('email', 'petr@test.ru')->firstOrFail();
        $this->assertAuthenticatedAs($user);
        $this->assertNull($user->email_verified_at);
        Notification::assertSentTo($user, VerifyEmail::class);
        $this->get('/app/new')->assertRedirect('/app/email-verification/prompt');
    }

    public function test_registration_closed_refuses_new_but_lets_linked_in(): void
    {
        config(['auth.registration_enabled' => false]);
        $this->fakeYandex(email: 'nobody@yandex.ru');
        $this->roundTrip('yandex')->assertRedirect('/app/login');
        $this->assertGuest();
        $this->get('/app/login')->assertSee('регистрация сейчас только по приглашению');

        SocialAccount::create(['user_id' => $this->admin->id, 'provider' => 'yandex', 'provider_user_id' => 'ya-777']);
        $this->roundTrip('yandex')->assertRedirect('/app');
        $this->assertAuthenticatedAs($this->admin);
    }

    public function test_blocked_user_cannot_log_in_with_provider(): void
    {
        $this->foreman->forceFill(['is_active' => false])->save();
        SocialAccount::create(['user_id' => $this->foreman->id, 'provider' => 'yandex', 'provider_user_id' => 'ya-777']);
        $this->fakeYandex();

        $this->roundTrip('yandex')->assertRedirect('/app/login');
        $this->assertGuest();
    }

    public function test_link_from_profile_and_refuse_account_of_other_user(): void
    {
        $this->fakeYandex(email: 'boss@yandex.ru');
        $this->actingAs($this->admin);
        $this->roundTrip('yandex', ['link' => true])->assertRedirect('/app/profile');
        $this->assertSame('ya-777', $this->admin->socialAccounts()->value('provider_user_id'));

        // тот же аккаунт Яндекса к другой учётке не привязать
        $this->actingAs($this->foreman);
        $this->roundTrip('yandex', ['link' => true])->assertRedirect('/app/profile')->assertSessionHas('social_error');
        $this->assertSame(0, $this->foreman->socialAccounts()->count());
    }

    public function test_last_login_method_cannot_be_unlinked_until_password_is_set(): void
    {
        $user = User::create(['name' => 'Соц', 'email' => 'soc@test.ru', 'password' => 'random-unknown']);
        $user->forceFill(['has_password' => false, 'email_verified_at' => now()])->save();
        $this->org->addMember($user, UserRole::Manager);
        SocialAccount::create(['user_id' => $user->id, 'provider' => 'yandex', 'provider_user_id' => 'ya-1']);
        $this->actingAs($user);
        $unlink = TestAction::make('unlink_yandex')->schemaComponent('social_yandex', schema: 'form');

        Livewire::test(EditProfile::class)
            ->assertSee('Задать пароль')
            ->assertActionDisabled($unlink)
            ->callAction($unlink);
        $this->assertSame(1, $user->socialAccounts()->count());

        // пароль задаётся без «текущего пароля»
        Livewire::test(EditProfile::class)
            ->fillForm(['password' => 'N3w-pass-123', 'passwordConfirmation' => 'N3w-pass-123'])
            ->call('save')->assertHasNoFormErrors();
        $user->refresh();
        $this->assertTrue($user->has_password);
        $this->assertTrue(Hash::check('N3w-pass-123', $user->password));

        Livewire::test(EditProfile::class)->assertActionEnabled($unlink)->callAction($unlink);
        $this->assertSame(0, $user->socialAccounts()->count());
    }

    public function test_password_reset_gives_social_user_a_password(): void
    {
        $user = User::create(['name' => 'Соц', 'email' => 'soc@test.ru', 'password' => 'random-unknown']);
        $user->forceFill(['has_password' => false])->save();
        event(new PasswordReset($user));
        $this->assertTrue($user->fresh()->has_password);
    }
}
