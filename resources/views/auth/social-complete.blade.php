@extends('layouts.simple')

@section('title', 'Завершение регистрации — Финпрораб')

@section('style')
    .complete { max-width: 520px; margin: 48px auto; }
    .complete h1 { font-size: 24px; margin: 0 0 8px; }
    .complete label.f { display: block; font-weight: 600; margin: 16px 0 6px; }
    .complete input[type=text], .complete input[type=email] { width: 100%; padding: 10px 12px; border: 1px solid var(--line); border-radius: 10px; font: inherit; }
    .complete .check { display: flex; gap: 10px; align-items: flex-start; margin-top: 14px; }
    .complete .check input { margin-top: 4px; width: 18px; height: 18px; }
    .complete .error { color: #b91c1c; font-size: 14px; margin-top: 6px; }
    .complete .actions { margin-top: 24px; display: flex; flex-wrap: wrap; gap: 12px; align-items: center; }
@endsection

@section('content')
<div class="wrap">
    <div class="card complete">
        <h1>Почти готово</h1>
        <p class="muted">Вы вошли через {{ $providerLabel }}. Это первый вход — проверьте имя и подтвердите согласие с документами.</p>

        @if ($emailTaken)
            <p class="error">Учётная запись с почтой {{ $social->email }} уже есть. Войдите по почте и паролю и привяжите {{ $providerLabel }} в профиле.</p>
            <div class="actions"><a class="btn" href="/app/login">Войти</a></div>
        @else
            <form method="post" action="{{ route('social.complete.store') }}">
                @csrf
                <label class="f" for="name">Имя</label>
                <input type="text" id="name" name="name" value="{{ old('name', $social->name) }}" required maxlength="255" autocomplete="name">
                @error('name')<div class="error">{{ $message }}</div>@enderror

                <label class="f" for="email">Почта</label>
                @if ($social->email)
                    <input type="email" id="email" value="{{ $social->email }}" disabled>
                    <div class="muted" style="font-size: 14px; margin-top: 6px">Почта из {{ $providerLabel }}, подтверждать её не нужно.</div>
                @else
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required maxlength="255" autocomplete="email">
                    <div class="muted" style="font-size: 14px; margin-top: 6px">{{ $providerLabel }} не передал почту. На неё придут письма сервиса — подтвердите её по ссылке из письма.</div>
                @endif
                @error('email')<div class="error">{{ $message }}</div>@enderror

                <label class="check">
                    <input type="checkbox" name="terms" value="1" @checked(old('terms'))>
                    <span>Принимаю <a href="{{ route('terms') }}" target="_blank">Пользовательское соглашение</a></span>
                </label>
                @error('terms')<div class="error">{{ $message }}</div>@enderror

                <label class="check">
                    <input type="checkbox" name="consent" value="1" @checked(old('consent'))>
                    <span>Даю <a href="{{ route('consent') }}" target="_blank">согласие на обработку персональных данных</a></span>
                </label>
                @error('consent')<div class="error">{{ $message }}</div>@enderror

                <div class="actions">
                    <button class="btn" type="submit">Создать учётную запись</button>
                    <a href="/app/login">Отмена</a>
                </div>
            </form>
        @endif
    </div>
</div>
@endsection
