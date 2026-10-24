<?php

namespace Tests\Feature;

use App\Filament\Auth\Register;
use App\Filament\Auth\RequestPasswordReset;
use App\Models\User;
use App\Support\AuthThrottle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;
use Tests\Feature\Concerns\SeedsAccounting;
use Tests\TestCase;

/** Публичная часть (/, /privacy, /terms), согласие при регистрации, SmartCaptcha, суточные лимиты */
class PublicAndRegistrationTest extends TestCase
{
    use RefreshDatabase, SeedsAccounting;

    private const FORM = ['name' => 'Новый', 'email' => 'new@test.ru', 'password' => 'Str0ng-pass!', 'passwordConfirmation' => 'Str0ng-pass!', 'terms' => true, 'consent' => true];

    protected function setUp(): void
    {
        parent::setUp();
        $this->useOrganization(null);
    }

    private function captchaOn(): void
    {
        config(['services.smartcaptcha.client_key' => 'client-key', 'services.smartcaptcha.server_key' => 'server-key']);
    }

    public function test_landing_for_guest_without_trackers(): void
    {
        $page = $this->get('/')->assertOk()
            ->assertSee('Сколько вы заработаете на каждом объекте')
            ->assertSee('/app/login', false)
            ->assertDontSee('/app/register', false)
            ->assertSee('Регистрация пока по приглашению')
            ->assertSee(route('privacy'), false)->assertSee(route('terms'), false);
        $this->assertDoesNotMatchRegularExpression('/<script[^>]+src="https?:/i', $page->getContent(), 'без внешних скриптов');

        config(['auth.registration_enabled' => true]);
        $this->get('/')->assertOk()->assertSee('Зарегистрироваться бесплатно')->assertDontSee('Регистрация пока по приглашению');
    }

    public function test_documents_show_version_and_mark_missing_operator_details(): void
    {
        $this->get('/privacy')->assertOk()
            ->assertSee('Политика обработки персональных данных')
            ->assertSee('Редакция от '.Carbon::parse(config('legal.version'))->format('d.m.Y'))
            ->assertSee('[заполнить: ИНН]')
            ->assertSee('Черновик.');
        $this->get('/terms')->assertOk()->assertSee('Пользовательское соглашение')->assertSee('[заполнить: ИНН]');

        config(['legal.draft' => false, 'legal.operator' => [
            'name' => 'ИП Тестов Тест Тестович', 'short' => 'ИП Тестов Т. Т.', 'inn' => '123456789012',
            'ogrn' => '312345678901234', 'address' => 'г. Саратов', 'email' => 'pd@test.ru',
        ]]);
        foreach (['/privacy', '/terms'] as $url) {
            $this->get($url)->assertOk()
                ->assertSee('ИП Тестов Тест Тестович')->assertSee('123456789012')->assertSee('mailto:pd@test.ru', false)
                ->assertDontSee('[заполнить')->assertDontSee('Черновик.');
        }
    }

    public function test_registration_requires_both_consents_and_stores_them(): void
    {
        config(['auth.registration_enabled' => true]);

        Livewire::test(Register::class)
            ->fillForm([...self::FORM, 'terms' => false, 'consent' => false])
            ->call('register')->assertHasFormErrors(['terms' => 'accepted', 'consent' => 'accepted']);
        $this->assertNull(User::where('email', 'new@test.ru')->first());

        Livewire::test(Register::class)
            ->assertSee('Пользовательское соглашение')->assertSee('Политики обработки персональных данных')
            ->fillForm(self::FORM)->call('register')->assertHasNoFormErrors();
        $user = User::where('email', 'new@test.ru')->firstOrFail();
        $this->assertNotNull($user->consent_at);
        $this->assertSame(config('legal.version'), $user->consent_version);
        $this->assertSame('127.0.0.1', $user->consent_ip);
    }

    public function test_without_keys_there_is_no_captcha(): void
    {
        config(['auth.registration_enabled' => true]);
        Http::fake();

        Livewire::test(Register::class)->assertFormFieldHidden('captcha')
            ->fillForm(self::FORM)->call('register')->assertHasNoFormErrors();
        Http::assertNothingSent();
    }

    public function test_captcha_on_registration(): void
    {
        config(['auth.registration_enabled' => true]);
        $this->captchaOn();
        Http::fake(['smartcaptcha.cloud.yandex.ru/*' => fn (Request $r) => Http::response($r['token'] === 'good-token'
            ? ['status' => 'ok', 'message' => '', 'host' => 'finprorab.ru']
            : ['status' => 'failed', 'message' => ''])]);

        $page = Livewire::test(Register::class)->assertFormFieldVisible('captcha')
            ->assertSee('smartcaptcha.cloud.yandex.ru/captcha.js', false);

        // нет токена — до Яндекса не доходим
        $page->fillForm(self::FORM)->call('register')->assertHasFormErrors(['captcha' => 'required']);
        Http::assertNothingSent();

        // робот
        $page->fillForm([...self::FORM, 'captcha' => 'bad-token'])->call('register')
            ->assertHasFormErrors(['captcha'])->assertDispatched('smart-captcha-reset');
        $this->assertNull(User::where('email', 'new@test.ru')->first());

        // человек (через минуту: Filament пускает не больше двух попыток в минуту)
        $this->travel(61)->seconds();
        $page->fillForm([...self::FORM, 'captcha' => 'good-token'])->call('register')->assertHasNoFormErrors();
        $this->assertNotNull(User::where('email', 'new@test.ru')->first());

        Http::assertSent(fn (Request $r) => $r->url() === 'https://smartcaptcha.cloud.yandex.ru/validate'
            && $r['secret'] === 'server-key' && $r['token'] === 'good-token' && $r['ip'] === '127.0.0.1');
    }

    public function test_captcha_service_failure_does_not_block_people(): void
    {
        config(['auth.registration_enabled' => true]);
        $this->captchaOn();
        Http::fake(['smartcaptcha.cloud.yandex.ru/*' => Http::response('Bad Gateway', 502)]);

        Livewire::test(Register::class)->fillForm([...self::FORM, 'captcha' => 'some-token'])
            ->call('register')->assertHasNoFormErrors();
        $this->assertNotNull(User::where('email', 'new@test.ru')->first());
    }

    public function test_captcha_on_password_reset(): void
    {
        $this->captchaOn();
        Notification::fake();
        Http::fake(['smartcaptcha.cloud.yandex.ru/*' => Http::response(['status' => 'failed', 'message' => ''])]);

        Livewire::test(RequestPasswordReset::class)->assertFormFieldVisible('captcha')
            ->fillForm(['email' => 'a@test.ru', 'captcha' => 'bad'])->call('request')
            ->assertHasFormErrors(['captcha'])->assertDispatched('smart-captcha-reset');
        Notification::assertNothingSent();
    }

    public function test_daily_limit_per_ip_for_registration_and_reset(): void
    {
        config(['auth.registration_enabled' => true]);
        Notification::fake();
        foreach (['register', 'password-reset'] as $action) {
            for ($i = 0; $i < AuthThrottle::PER_DAY; $i++) {
                RateLimiter::hit('auth-day:'.$action.':127.0.0.1', 86400);
            }
        }

        Livewire::test(Register::class)->fillForm(self::FORM)->call('register')->assertNotified('Слишком много попыток');
        $this->assertNull(User::where('email', 'new@test.ru')->first());

        Livewire::test(RequestPasswordReset::class)->fillForm(['email' => 'a@test.ru'])->call('request')->assertNotified('Слишком много попыток');
        Notification::assertNothingSent();
    }
}
