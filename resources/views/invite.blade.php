@extends('layouts.simple')

@section('title', 'Приглашение — Учёт объектов')

@section('style')
    .invite { max-width: 520px; margin: 48px auto; }
    .invite h1 { font-size: 24px; margin: 0 0 12px; }
    .invite .actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 20px; }
    .invite .error { color: #b91c1c; }
@endsection

@section('content')
<div class="wrap">
    <div class="card invite">
        @if (! $invite)
            <h1>Приглашение недействительно</h1>
            <p class="error">{{ $error }}</p>
            <div class="actions"><a class="btn ghost" href="/app/login">Войти</a></div>
        @else
            <h1>Приглашение в «{{ $invite->organization->name }}»</h1>
            <p>Роль: <b>{{ $invite->role->getLabel() }}</b>. Приглашение действует до {{ $invite->expires_at->format('d.m.Y H:i') }}.</p>
            @if ($error)
                <p class="error">{{ $error }}</p>
                <form method="post" action="{{ route('filament.admin.auth.logout') }}">@csrf<button class="btn ghost" type="submit">Выйти</button></form>
            @elseif (auth()->check())
                <p class="muted">Вы вошли как {{ auth()->user()->name }} ({{ auth()->user()->email }}).</p>
                <form method="post" action="{{ route('invite.accept', $invite->id) }}" class="actions">
                    @csrf
                    <button class="btn" type="submit">Принять приглашение</button>
                    <a class="btn ghost" href="/app">Не сейчас</a>
                </form>
            @else
                <p class="muted">Чтобы принять приглашение, войдите в свою учётную запись или зарегистрируйтесь@if ($invite->email) с почтой {{ $invite->email }}@endif.</p>
                <div class="actions">
                    <a class="btn" href="/app/register">Зарегистрироваться</a>
                    <a class="btn ghost" href="/app/login">У меня есть учётная запись</a>
                </div>
            @endif
        @endif
    </div>
</div>
@endsection
