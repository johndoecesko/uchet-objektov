@extends('layouts.simple')

@section('nav')
    @auth
        <a class="btn" href="/app">Открыть учёт</a>
    @else
        <a class="btn ghost" href="/app/login">Войти</a>
    @endauth
@endsection

@section('style')
    .doc { max-width: 820px; margin: 32px auto; }
    .doc h1 { font-size: 28px; line-height: 1.25; margin: 0 0 8px; }
    .doc h2 { font-size: 20px; margin: 28px 0 8px; }
    .doc p, .doc li { margin: 0 0 10px; }
    .doc ol, .doc ul { padding-left: 22px; }
    .doc .meta { color: var(--muted); font-size: 14px; margin-bottom: 20px; }
    .draft { background: #fef3c7; border: 1px solid #f59e0b; color: #78350f; border-radius: 12px; padding: 12px 16px; margin-bottom: 20px; font-size: 15px; }
    mark.todo { background: #fee2e2; color: #991b1b; padding: 0 4px; border-radius: 4px; }
@endsection

@section('content')
<div class="wrap">
    <article class="card doc">
        @if (config('legal.draft'))
            <div class="draft"><b>Черновик.</b> Текст подготовлен как основа и должен быть проверен юристом до публичного запуска регистрации.</div>
        @endif
        @yield('doc')
    </article>
</div>
@endsection
