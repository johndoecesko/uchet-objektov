@extends('layouts.simple')

@section('title', 'Установить на телефон — Финпрораб')
@section('description', 'Как установить Финпрораб на телефон Android или iPhone — без магазина приложений.')

@section('nav')
    @auth
        <a class="btn" href="/app">Открыть учёт</a>
    @else
        <a class="btn ghost" href="/app/login">Войти</a>
    @endauth
@endsection

@section('content')
@include('partials.install')
@endsection
