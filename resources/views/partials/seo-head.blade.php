{{-- SEO и превью ссылок: канонический адрес, Open Graph, подтверждение Вебмастера. Страницы с @section('robots', 'noindex') не индексируются --}}
@php($title = trim($__env->yieldContent('title', 'Финпрораб — учёт объектов')))
@php($description = trim($__env->yieldContent('description', 'Учёт денег по монтажным объектам: расходы, зарплата, поступления, прибыль')))
@hasSection('robots')
    <meta name="robots" content="@yield('robots')">
@else
    <link rel="canonical" href="{{ url()->current() }}">
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="Финпрораб">
    <meta property="og:locale" content="ru_RU">
    <meta property="og:title" content="{{ $title }}">
    <meta property="og:description" content="{{ $description }}">
    <meta property="og:url" content="{{ url()->current() }}">
    <meta property="og:image" content="{{ url('/img/og.png') }}">
    <meta property="og:image:width" content="1200">
    <meta property="og:image:height" content="630">
    <meta name="twitter:card" content="summary_large_image">
@endif
@if ($verification = config('services.yandex_verification'))
    <meta name="yandex-verification" content="{{ $verification }}">
@endif
<link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
