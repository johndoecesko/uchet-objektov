@extends('layouts.simple')

@section('title', 'Демо — Финпрораб')
@section('robots', 'noindex, nofollow')

@section('style')
    .demo { max-width: 560px; margin: 48px auto; }
    .demo h1 { font-size: 26px; margin: 0 0 12px; }
    .demo ul { padding-left: 20px; margin: 12px 0 20px; }
    .demo .error { color: #b91c1c; }
    .demo .smart-captcha { margin: 12px 0; min-height: 102px; }
@endsection

@section('content')
<div class="wrap">
    <div class="card demo">
        <h1>Посмотреть демо</h1>
        <p>Вымышленная монтажная организация «Демо-Монтаж»: четыре объекта, бригада, расходы с чеками, журнал работ, поступления от заказчиков и прогноз прибыли.</p>
        <ul class="muted">
            <li>без регистрации и пароля;</li>
            <li>можно открыть все разделы и скачать Excel, изменить ничего нельзя;</li>
            <li>данные обновляются каждую ночь.</li>
        </ul>
        @error('captcha')<p class="error">{{ $message }}</p>@enderror
        <form method="post" action="{{ route('demo.enter') }}">
            @csrf
            @if ($captcha)
                <div class="smart-captcha" data-sitekey="{{ \App\Support\SmartCaptcha::clientKey() }}" data-hl="ru"></div>
                @push('head')<script src="https://smartcaptcha.cloud.yandex.ru/captcha.js" defer></script>@endpush
            @endif
            <button class="btn" type="submit">Открыть демо</button>
            @if ($registrationOpen)
                <a class="btn ghost" href="/app/register">Зарегистрироваться</a>
            @endif
        </form>
    </div>
</div>
@endsection
