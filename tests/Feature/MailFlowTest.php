<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Filament\Auth\EditProfile;
use App\Filament\Auth\Register;
use App\Filament\Auth\RequestPasswordReset;
use App\Models\Invitation;
use App\Models\User;
use App\Notifications\Auth\ResetPassword;
use App\Notifications\Auth\VerifyEmail;
use App\Notifications\Auth\VerifyEmailChange;
use App\Notifications\OrganizationInvitation;
use Filament\Auth\Notifications\NoticeOfEmailChangeRequest;
use Filament\Auth\Pages\PasswordReset\ResetPassword as ResetPasswordPage;
use Filament\Facades\Filament;
use Illuminate\Contracts\Validation\UncompromisedVerifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\AnonymousNotifiable;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\Rules\Password;
use Livewire\Livewire;
use ReflectionProperty;
use Tests\Feature\Concerns\SeedsAccounting;
use Tests\TestCase;

/** Почта: подтверждение после регистрации, сброс пароля, смена почты в профиле, русские письма */
class MailFlowTest extends TestCase
{
    use RefreshDatabase, SeedsAccounting;

    public function test_registration_sends_russian_verification_email_and_panel_waits_for_it(): void
    {
        config(['auth.registration_enabled' => true]);
        Notification::fake();
        $this->useOrganization(null);

        Livewire::test(Register::class)
            ->fillForm(['name' => 'Новый', 'email' => 'New@Test.ru', 'password' => 'Str0ng-pass!', 'passwordConfirmation' => 'Str0ng-pass!', 'terms' => true, 'consent' => true])
            ->call('register')->assertHasNoFormErrors();
        $user = User::where('email', 'new@test.ru')->firstOrFail();
        $this->assertNull($user->email_verified_at);

        $url = null;
        Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $n) use ($user, &$url) {
            $mail = $n->toMail($user);
            $url = $n->url;

            return $mail->subject === 'Подтвердите почту — Учёт объектов' && $mail->actionUrl === $n->url;
        });

        // без подтверждения даже организацию не создать
        $this->actingAs($user)->get('/app/new')->assertRedirect('/app/email-verification/prompt');
        $this->get('/app/email-verification/prompt')->assertOk()->assertSee('new@test.ru');

        $this->get($url)->assertRedirect();
        $this->assertNotNull($user->fresh()->email_verified_at);
        // подтвердил — экран «Создайте организацию»
        $this->get('/app/new')->assertOk();
    }

    public function test_unverified_member_cannot_open_organization(): void
    {
        $this->foreman->forceFill(['email_verified_at' => null])->save();
        $this->actingAs($this->foreman)->get('/app/montazh/expenses')->assertRedirect('/app/email-verification/prompt');
    }

    public function test_verification_link_with_bad_signature_does_not_work(): void
    {
        $user = $this->member('Неподтверждённый', 'u@test.ru', UserRole::Manager);
        $user->forceFill(['email_verified_at' => null])->save();
        $url = Filament::getPanel('admin')->getVerifyEmailUrl($user);

        $this->actingAs($user)->get($url.'x')->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_verification_link_of_other_user_does_not_work(): void
    {
        $user = $this->member('Неподтверждённый', 'u@test.ru', UserRole::Manager);
        $user->forceFill(['email_verified_at' => null])->save();

        $this->actingAs($this->foreman)->get(Filament::getPanel('admin')->getVerifyEmailUrl($user))->assertForbidden();
        $this->assertNull($user->fresh()->email_verified_at);
    }

    public function test_existing_verified_member_is_not_bothered(): void
    {
        $this->actingAs($this->admin)->get('/app/montazh')->assertOk();
    }

    public function test_password_reset_by_email(): void
    {
        Notification::fake();
        $this->useOrganization(null);
        $this->get('/app/login')->assertSee('Забыли свой пароль?');

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => 'a@test.ru'])->call('request')
            ->assertNotified('Если такая почта зарегистрирована, на неё отправлено письмо со ссылкой для сброса пароля.');

        $sent = null;
        Notification::assertSentTo($this->admin, ResetPassword::class, function (ResetPassword $n) use (&$sent) {
            $sent = $n;

            return $n->toMail($this->admin)->subject === 'Сброс пароля — Учёт объектов';
        });
        $this->assertStringContainsString('/app/password-reset/reset', $sent->url);

        Livewire::test(ResetPasswordPage::class, ['email' => 'a@test.ru', 'token' => $sent->token])
            ->fillForm(['password' => 'N3w-strong-pass', 'passwordConfirmation' => 'N3w-strong-pass'])
            ->call('resetPassword')->assertHasNoFormErrors()
            ->assertNotified('Пароль изменён. Войдите с новым паролем.');
        $this->assertTrue(Hash::check('N3w-strong-pass', $this->admin->fresh()->password));

        // ссылка одноразовая
        Livewire::test(ResetPasswordPage::class, ['email' => 'a@test.ru', 'token' => $sent->token])
            ->fillForm(['password' => 'An0ther-pass!', 'passwordConfirmation' => 'An0ther-pass!'])
            ->call('resetPassword')
            ->assertNotified('Ссылка для сброса пароля недействительна или устарела. Запросите новую.');
        $this->assertTrue(Hash::check('N3w-strong-pass', $this->admin->fresh()->password));
    }

    public function test_password_reset_does_not_reveal_unknown_email_and_password_is_at_least_8(): void
    {
        Notification::fake();
        $this->useOrganization(null);

        Livewire::test(RequestPasswordReset::class)
            ->fillForm(['email' => 'nobody@test.ru'])->call('request')
            ->assertNotified('Если такая почта зарегистрирована, на неё отправлено письмо со ссылкой для сброса пароля.');
        Notification::assertNothingSent();

        config(['auth.registration_enabled' => true]);
        Livewire::test(Register::class)
            ->fillForm(['name' => 'Коротко', 'email' => 'short@test.ru', 'password' => 'Ab1-xyz', 'passwordConfirmation' => 'Ab1-xyz', 'terms' => true, 'consent' => true])
            ->call('register')->assertHasFormErrors(['password']);
    }

    public function test_blocked_user_gets_no_reset_link(): void
    {
        Notification::fake();
        $this->useOrganization(null);
        $this->foreman->forceFill(['is_active' => false])->save();

        Livewire::test(RequestPasswordReset::class)->fillForm(['email' => 'f@test.ru'])->call('request')
            ->assertNotified('Если такая почта зарегистрирована, на неё отправлено письмо со ссылкой для сброса пароля.');
        Notification::assertNothingSent();
    }

    public function test_profile_page_opens_from_user_menu(): void
    {
        // профиль — вне адреса организации (/app/profile); меню организаций при этом показывает текущую
        $this->useOrganization(null);
        $this->actingAs($this->admin)->get('/app/profile')->assertOk()->assertSee('a@test.ru')->assertSee('ООО «Монтаж»');
    }

    public function test_profile_without_organization_leads_to_creating_one(): void
    {
        $this->useOrganization(null);
        $loner = User::create(['name' => 'Один', 'email' => 'one@test.ru', 'password' => 'secret123']);
        $loner->forceFill(['email_verified_at' => now()])->save();
        $this->actingAs($loner)->get('/app/profile')->assertRedirect('/app/new');
    }

    public function test_email_change_takes_effect_only_after_link_from_new_address(): void
    {
        Notification::fake();
        $this->actingAs($this->admin);

        Livewire::test(EditProfile::class)
            ->fillForm(['email' => 'new-admin@test.ru', 'currentPassword' => 'secret123'])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame('a@test.ru', $this->admin->fresh()->email, 'до подтверждения почта прежняя');

        // на старую — уведомление с кнопкой «заблокировать», на новую — ссылка подтверждения
        Notification::assertSentTo($this->admin, NoticeOfEmailChangeRequest::class);
        $url = null;
        Notification::assertSentOnDemand(VerifyEmailChange::class, function (VerifyEmailChange $n, array $channels, AnonymousNotifiable $to) use (&$url) {
            $url = $n->url;

            return $to->routes['mail'] === 'new-admin@test.ru'
                && $n->toMail($to)->subject === 'Подтвердите новую почту — Учёт объектов';
        });

        $this->get($url)->assertRedirect();
        $this->assertSame('new-admin@test.ru', $this->admin->fresh()->email);
    }

    public function test_leaked_password_check_waits_at_most_5_seconds_and_says_it_in_russian(): void
    {
        $verifier = app(UncompromisedVerifier::class);
        $this->assertSame(5, (new ReflectionProperty($verifier, 'timeout'))->getValue($verifier));

        Http::fake(['api.pwnedpasswords.com/*' => Http::response(strtoupper(substr(sha1('Str0ng-pass!'), 5)).':12')]);
        $v = validator(['password' => 'Str0ng-pass!'], ['password' => [Password::min(8)->uncompromised()]]);
        $this->assertSame('Такой пароль встречался в утечках баз данных — придумайте другой.', $v->errors()->first('password'));
    }

    public function test_letters_are_russian_without_english_template_lines(): void
    {
        $reset = app(\Filament\Auth\Notifications\ResetPassword::class, ['token' => 't']);
        $this->assertInstanceOf(ResetPassword::class, $reset, 'Filament берёт наше письмо из контейнера');
        $reset->url = 'https://finprorab.ru/app/password-reset/reset?token=t';

        $invite = Invitation::create(['organization_id' => $this->org->id, 'role' => UserRole::Foreman, 'created_by' => $this->admin->id, 'expires_at' => now()->addDays(7)]);
        $letters = [
            $reset->toMail($this->admin),
            tap(app(\Filament\Auth\Notifications\VerifyEmail::class), fn ($n) => $n->url = 'https://x/verify')->toMail($this->admin),
            (new NoticeOfEmailChangeRequest('n@test.ru', 'https://x/block'))->toMail($this->admin),
            (new OrganizationInvitation($invite, $this->org, $this->admin))->toMail(new AnonymousNotifiable),
        ];

        foreach ($letters as $mail) {
            $html = (string) $mail->render();
            $this->assertStringContainsString('Учёт объектов', $html);
            $this->assertStringContainsString('Если кнопка «', $html);
            $this->assertStringContainsString('Все права защищены.', $html);
            foreach (['Hello', 'Regards', 'trouble clicking', 'All rights reserved', 'Laravel'] as $english) {
                $this->assertStringNotContainsString($english, $html);
            }
        }
    }
}
