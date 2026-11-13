<?php

namespace App\Http\Controllers;

use App\Models\SocialAccount;
use App\Models\User;
use App\Support\Registration;
use App\Support\Social\Provider;
use App\Support\Social\SocialAuthFailed;
use App\Support\Social\SocialUser;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Вход через Яндекс ID и VK ID.
 *   /auth/{provider}/redirect        → на страницу провайдера (state + PKCE в сессии); ?link=1 из профиля — привязать к себе
 *   /auth/{provider}/callback        → привязка есть — вход; нет, но подтверждённая почта совпала с учёткой — привязать и войти;
 *                                      иначе — новая учётка через экран согласия (/auth/complete)
 *   /auth/complete                   → имя, почта (если провайдер её не дал), две отметки согласия
 */
class SocialAuthController extends Controller
{
    private const FLOW = 'social.flow';

    private const PENDING = 'social.pending';

    public function redirect(Request $request, string $provider): RedirectResponse
    {
        $p = $this->provider($provider);
        // Демо-учётка общая: привязать к ней Яндекс ID / VK ID — отдать её любому гостю (маршрут закрыт и в DemoGuard)
        abort_if($request->user()?->is_demo, 403);
        $link = $request->boolean('link') && $request->user() !== null;

        $state = Str::random(40);
        $verifier = Str::random(64);
        session([self::FLOW => [
            'provider' => $p->name(),
            'state' => $state,
            'verifier' => $verifier,
            'link_user_id' => $link ? $request->user()->id : null,
        ]]);

        return new RedirectResponse($p->authorizeUrl($state, Provider::codeChallenge($verifier)));
    }

    public function callback(Request $request, string $provider): RedirectResponse
    {
        $p = $this->provider($provider);
        $flow = session()->pull(self::FLOW);

        if (! is_array($flow) || $flow['provider'] !== $p->name() || ! hash_equals($flow['state'], (string) $request->query('state'))) {
            return $this->fail('Вход не удался: ссылка устарела или открыта в другом браузере. Попробуйте ещё раз.');
        }
        if ($request->filled('error')) {
            return $this->fail('Вход через '.$p->label().' отменён.');
        }

        try {
            $social = $p->user($request, $flow['verifier'], $flow['state']);
        } catch (SocialAuthFailed $e) {
            Log::warning($e->getMessage());

            return $this->fail($p->label().' не ответил. Попробуйте ещё раз или войдите по почте и паролю.');
        }

        $account = SocialAccount::where('provider', $social->provider)->where('provider_user_id', $social->id)->first();

        // Привязка из профиля
        if ($flow['link_user_id']) {
            return $this->link($request, (int) $flow['link_user_id'], $social, $account);
        }

        if ($account) {
            return $this->login($account->user);
        }

        // Почта подтверждена провайдером и такая учётка есть — это тот же человек (демо-учётки не в счёт)
        if ($social->email && $social->emailVerified && ($user = User::where('email', $social->email)->where('is_demo', false)->first())) {
            $this->attach($user, $social);
            if (! $user->email_verified_at) {
                $user->forceFill(['email_verified_at' => now()])->save();
            }

            return $this->login($user);
        }

        if (! Registration::allowed()) {
            return $this->fail('Учётной записи, привязанной к этому аккаунту '.$p->label().', нет, а регистрация сейчас только по приглашению.');
        }

        session([self::PENDING => $social->toArray()]);

        return new RedirectResponse(route('social.complete'));
    }

    public function completeForm(): View|RedirectResponse
    {
        $social = $this->pending();
        if (! $social) {
            return new RedirectResponse(url('/app/login'));
        }

        return view('auth.social-complete', [
            'social' => $social,
            'providerLabel' => Provider::find($social->provider)?->label(),
            'emailTaken' => $social->email && User::where('email', $social->email)->exists(),
        ]);
    }

    public function complete(Request $request): RedirectResponse
    {
        $social = $this->pending();
        if (! $social) {
            return new RedirectResponse(url('/app/login'));
        }
        abort_unless(Registration::allowed(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => $social->email ? ['nullable'] : ['required', 'email', 'max:255', Rule::unique('users', 'email')],
            'terms' => ['accepted'],
            'consent' => ['accepted'],
        ], [
            'email.unique' => 'Учётная запись с этой почтой уже есть. Войдите по почте и паролю и привяжите аккаунт в профиле.',
            'terms.accepted' => 'Без согласия с Пользовательским соглашением зарегистрироваться нельзя.',
            'consent.accepted' => 'Без согласия на обработку персональных данных зарегистрироваться нельзя.',
        ], ['name' => 'Имя', 'email' => 'Почта']);

        $email = $social->email ?? mb_strtolower(trim($data['email']));
        if (User::where('email', $email)->exists()) {
            return (new RedirectResponse(route('social.complete')))->withErrors(['email' => 'Учётная запись с этой почтой уже есть. Войдите по почте и паролю и привяжите аккаунт в профиле.']);
        }

        $user = DB::transaction(function () use ($data, $email, $social, $request) {
            $user = User::create(['name' => $data['name'], 'email' => $email, 'password' => Str::random(48)]);
            $user->forceFill([
                'has_password' => false,
                // почта от провайдера подтверждена им; введённую вручную подтверждает письмо
                'email_verified_at' => $social->email ? now() : null,
                'consent_at' => now(),
                'consent_version' => config('legal.version'),
                'consent_ip' => $request->ip(),
            ])->save();
            $this->attach($user, $social);

            return $user;
        });
        session()->forget(self::PENDING);

        if (! $user->email_verified_at) {
            $notification = app(\Filament\Auth\Notifications\VerifyEmail::class);
            $notification->url = Filament::getPanel('admin')->getVerifyEmailUrl($user);
            $user->notify($notification);
        }

        return $this->login($user);
    }

    private function link(Request $request, int $userId, SocialUser $social, ?SocialAccount $account): RedirectResponse
    {
        $user = $request->user();
        if (! $user || $user->id !== $userId || $user->is_demo) {
            return $this->fail('Привязка не удалась: войдите и попробуйте снова из профиля.');
        }

        $label = Provider::find($social->provider)?->label();
        if ($account && $account->user_id !== $user->id) {
            return $this->toProfile('Этот аккаунт '.$label.' уже привязан к другой учётной записи.', false);
        }
        if (! $account) {
            $this->attach($user, $social);
        }

        return $this->toProfile($label.' привязан: теперь можно входить через него.', true);
    }

    private function attach(User $user, SocialUser $social): void
    {
        SocialAccount::updateOrCreate(
            ['user_id' => $user->id, 'provider' => $social->provider],
            ['provider_user_id' => $social->id, 'email' => $social->email],
        );
    }

    private function login(User $user): RedirectResponse
    {
        if ($user->is_demo) {
            return $this->fail('Вход не удался. Попробуйте войти по почте и паролю.');
        }
        if (! $user->is_active) {
            return $this->fail('Учётная запись отключена. Напишите нам: '.config('app.support_email'));
        }

        Auth::guard(Filament::getPanel('admin')->getAuthGuard())->login($user, remember: true);
        session()->regenerate();

        return new RedirectResponse(url(session()->pull('url.intended', '/app')));
    }

    private function fail(string $message): RedirectResponse
    {
        session()->flash('social_error', $message);

        return new RedirectResponse(url(auth()->check() ? '/app/profile' : '/app/login'));
    }

    private function toProfile(string $message, bool $ok): RedirectResponse
    {
        session()->flash($ok ? 'social_status' : 'social_error', $message);

        return new RedirectResponse(url('/app/profile'));
    }

    private function provider(string $name): Provider
    {
        $p = Provider::find($name);
        abort_unless($p?->enabled(), 404);

        return $p;
    }

    private function pending(): ?SocialUser
    {
        $data = session(self::PENDING);

        return is_array($data) ? SocialUser::fromArray($data) : null;
    }
}
