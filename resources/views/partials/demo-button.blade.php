{{-- «Посмотреть демо» (главная): форма POST /demo — от гостя только CSRF. С капчей (DEMO_CAPTCHA) — сначала страница /demo.
     Цель Метрики demo_open — только если посетитель согласился на cookie (ym есть лишь после согласия). --}}
@if (config('demo.enabled'))
    @php($metrikaId = (int) config('services.yandex_metrika.id'))
    @if (config('demo.captcha') && \App\Support\SmartCaptcha::enabled())
        <a class="btn {{ $class ?? 'ghost' }}" href="{{ route('demo') }}" @if ($metrikaId) onclick="window.ym && ym({{ $metrikaId }}, 'reachGoal', 'demo_open')" @endif>Посмотреть демо</a>
    @else
        <form method="post" action="{{ route('demo.enter') }}" style="display: inline; margin: 0" @if ($metrikaId) onsubmit="window.ym && ym({{ $metrikaId }}, 'reachGoal', 'demo_open')" @endif>
            @csrf
            <button class="btn {{ $class ?? 'ghost' }}" type="submit">Посмотреть демо</button>
        </form>
    @endif
@endif
