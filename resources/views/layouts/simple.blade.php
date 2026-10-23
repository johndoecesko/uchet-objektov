<!doctype html>
<html lang="ru">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'Учёт объектов')</title>
    <meta name="description" content="@yield('description', 'Учёт денег по монтажным объектам: расходы, зарплата, поступления, прибыль')">
    <link rel="icon" href="/icons/favicon-32.png">
    <link rel="manifest" href="/manifest.json">
    <meta name="theme-color" content="#2563eb">
    <style>
        :root { --blue: #2563eb; --blue-dark: #1d4ed8; --text: #0f172a; --muted: #475569; --line: #e2e8f0; --bg: #f8fafc; }
        * { box-sizing: border-box; }
        body { margin: 0; font: 16px/1.55 system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: var(--text); background: var(--bg); }
        a { color: var(--blue); }
        .wrap { max-width: 1040px; margin: 0 auto; padding: 0 16px; }
        header.top { background: #fff; border-bottom: 1px solid var(--line); }
        header.top .wrap { display: flex; align-items: center; justify-content: space-between; gap: 12px; min-height: 60px; }
        .brand { display: flex; align-items: center; gap: 10px; font-weight: 700; color: var(--text); text-decoration: none; font-size: 18px; }
        .brand img { width: 32px; height: 32px; border-radius: 8px; }
        .btn { display: inline-block; padding: 10px 18px; border-radius: 10px; background: var(--blue); color: #fff; text-decoration: none; font-weight: 600; border: 0; cursor: pointer; font-size: 16px; }
        .btn:hover { background: var(--blue-dark); }
        .btn.ghost { background: transparent; color: var(--blue); border: 1px solid var(--blue); }
        .card { background: #fff; border: 1px solid var(--line); border-radius: 16px; padding: 24px; }
        .muted { color: var(--muted); }
        footer.bottom { border-top: 1px solid var(--line); margin-top: 48px; padding: 24px 0; font-size: 14px; color: var(--muted); }
        footer.bottom .wrap { display: flex; flex-wrap: wrap; gap: 8px 20px; justify-content: space-between; }
        footer.bottom a { color: var(--muted); }
        @yield('style')
    </style>
</head>
<body>
<header class="top">
    <div class="wrap">
        <a class="brand" href="/"><img src="/icons/icon-192.png" alt="">Учёт объектов</a>
        <nav>@yield('nav')</nav>
    </div>
</header>
<main>@yield('content')</main>
<footer class="bottom">
    <div class="wrap">
        <span>© {{ date('Y') }} «Учёт объектов»</span>
        <span>
            @if (\Illuminate\Support\Facades\Route::has('privacy'))<a href="{{ route('privacy') }}">Политика обработки персональных данных</a> · <a href="{{ route('terms') }}">Пользовательское соглашение</a> · @endif
            Связь: <a href="https://t.me/{{ ltrim(config('app.support_telegram'), '@') }}">{{ config('app.support_telegram') }}</a>, <a href="mailto:{{ config('app.support_email') }}">{{ config('app.support_email') }}</a>
        </span>
    </div>
</footer>
</body>
</html>
