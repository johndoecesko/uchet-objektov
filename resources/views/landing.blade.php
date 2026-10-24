@extends('layouts.simple')

@section('title', 'Финпрораб — учёт денег по монтажным объектам без Excel')
@section('description', 'Финпрораб — бесплатный сервис учёта объектов для монтажных организаций: бюджет и расходы по объектам, фото чеков, журнал работ и зарплата, подотчёт, поступления от заказчика, прогноз прибыли.')

@php($open = \App\Support\Registration::open())

@section('nav')
    @auth
        <a class="btn" href="/app">Открыть учёт</a>
    @else
        <a class="btn ghost" href="/app/login">Войти</a>
        @if ($open)<a class="btn nav-reg" href="/app/register">Зарегистрироваться</a>@endif
    @endauth
@endsection

@section('style')
    nav { display: flex; gap: 8px; }
    .hero { padding: 56px 0 40px; display: grid; grid-template-columns: 1.1fr 1fr; gap: 40px; align-items: center; }
    .hero h1 { font-size: 40px; line-height: 1.15; margin: 0 0 16px; letter-spacing: -0.02em; }
    .hero p.lead { font-size: 19px; color: var(--muted); margin: 0 0 24px; }
    .hero .actions { display: flex; flex-wrap: wrap; gap: 12px; align-items: center; }
    .hero .note { font-size: 14px; color: var(--muted); margin-top: 12px; }
    .shot { display: block; width: 100%; height: auto; border-radius: 12px; border: 1px solid var(--line); box-shadow: 0 10px 30px rgba(15, 23, 42, .08); background: #fff; }
    section.block { padding: 32px 0; }
    section.block h2 { font-size: 28px; margin: 0 0 8px; letter-spacing: -0.01em; }
    section.block > .wrap > p.muted { margin: 0 0 24px; max-width: 720px; }
    .grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }
    .feature { padding: 20px; }
    .feature h3 { margin: 0 0 6px; font-size: 17px; }
    .feature p { margin: 0; color: var(--muted); font-size: 15px; }
    .shots { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
    .shots figure { margin: 0; }
    .shots figcaption { font-size: 14px; color: var(--muted); margin-top: 8px; }
    .mobile { display: grid; grid-template-columns: 260px 1fr; gap: 32px; align-items: center; }
    .mobile ul { margin: 0; padding-left: 20px; }
    .mobile li { margin-bottom: 8px; }
    table.formula { width: 100%; border-collapse: collapse; background: #fff; border: 1px solid var(--line); border-radius: 12px; overflow: hidden; font-size: 15px; }
    table.formula td { padding: 10px 14px; border-top: 1px solid var(--line); vertical-align: top; }
    table.formula tr:first-child td { border-top: 0; }
    table.formula td:first-child { font-weight: 600; width: 36%; }
    .cta { text-align: center; padding: 40px 24px; }
    .cta h2 { margin: 0 0 8px; }
    .cta p { margin: 0 0 20px; color: var(--muted); }
    @media (max-width: 860px) {
        .hero { grid-template-columns: 1fr; padding-top: 32px; gap: 24px; }
        .hero h1 { font-size: 30px; }
        .grid { grid-template-columns: 1fr 1fr; }
        .shots { grid-template-columns: 1fr; }
        .mobile { grid-template-columns: 1fr; }
        .mobile img { max-width: 260px; margin: 0 auto; }
    }
    @media (max-width: 560px) {
        .grid { grid-template-columns: 1fr; }
        .nav-reg { display: none; }
        table.formula td:first-child { width: auto; }
    }
@endsection

@section('content')
<div class="wrap">
    <div class="hero">
        <div>
            <h1>Сколько вы заработаете на каждом объекте</h1>
            <p class="lead">Бюджет и расходы по объектам, фото чеков, журнал работ и зарплата, подотчёт и оплаты заказчика — в одном месте вместо таблиц и чеков в мессенджерах.</p>
            <div class="actions">
                @auth
                    <a class="btn" href="/app">Открыть учёт</a>
                @else
                    @if ($open)
                        <a class="btn" href="/app/register">Зарегистрироваться бесплатно</a>
                    @endif
                    <a class="btn ghost" href="/app/login">Войти</a>
                @endauth
            </div>
            <p class="note">Для монтажных и строительных компаний: пожарная сигнализация, видеонаблюдение, СКУД, слаботочка, электромонтаж.@unless ($open) Регистрация пока по приглашению — напишите нам.@endunless</p>
        </div>
        <img class="shot" src="/img/landing/01-dashboard.png" width="1440" height="1028" alt="Главная: «Требует внимания», сводка за месяц и объекты в работе">
    </div>
</div>

<section class="block">
    <div class="wrap">
        <h2>Что умеет</h2>
        <p class="muted">Каждый вносит своё: прораб — расходы и работы с телефона, офис — оплаты и зарплату. Руководитель видит экономику объектов в реальном времени.</p>
        <div class="grid">
            <div class="card feature"><h3>Объекты и бюджет</h3><p>Договор, заказчик по ИНН, бюджет по статьям затрат. План/факт по каждой статье.</p></div>
            <div class="card feature"><h3>Расходы с фото чеков</h3><p>Материалы, субподряд, транспорт, аренда. Источник оплаты: счёт, карта, касса или подотчёт.</p></div>
            <div class="card feature"><h3>Чеки через бота в MAX</h3><p>Фото чека боту, три ответа кнопками — расход уже в учёте вместе с фото.</p></div>
            <div class="card feature"><h3>Журнал работ и зарплата</h3><p>Кто, где и сколько дней работал. Зарплата считается сама и ложится в затраты объекта.</p></div>
            <div class="card feature"><h3>Выплаты и подотчёт</h3><p>Долг по зарплате и остаток подотчёта по каждому сотруднику, расчётный листок в Excel.</p></div>
            <div class="card feature"><h3>Оплаты заказчика</h3><p>Аванс, этапы, окончательный расчёт и долг заказчика. Налог — процентом от договора.</p></div>
            <div class="card feature"><h3>«Требует внимания»</h3><p>Перерасход по статье, объект в минусе, низкая рентабельность, старый подотчёт, долг по сданному объекту.</p></div>
            <div class="card feature"><h3>Выгрузки в Excel</h3><p>По объекту, отчёт собственнику за период, расчётный листок. Числа — числами, даты — датами.</p></div>
            <div class="card feature"><h3>Роли и история</h3><p>Администратор, бухгалтер, прораб — каждый видит своё. Кто и что поменял — «было → стало».</p></div>
        </div>
    </div>
</section>

<section class="block">
    <div class="wrap">
        <h2>План/факт и прогноз прибыли</h2>
        <p class="muted">Перерасход по статье виден сразу — до того, как объект уйдёт в минус.</p>
        <div class="shots">
            <figure>
                <img class="shot" src="/img/landing/02-project-solnechny.png" width="1440" height="900" loading="lazy" alt="Карточка объекта: план/факт и прогноз прибыли">
                <figcaption>Карточка объекта: бюджет по статьям, освоение и прогноз</figcaption>
            </figure>
            <figure>
                <img class="shot" src="/img/landing/03-project-school-overrun.png" width="1440" height="900" loading="lazy" alt="Объект с перерасходом по материалам">
                <figcaption>Перерасход по материалам подсвечен красным</figcaption>
            </figure>
        </div>
    </div>
</section>

<section class="block">
    <div class="wrap mobile">
        <img class="shot" src="/img/landing/04-mobile-new-expense.png" width="390" height="844" loading="lazy" alt="Новый расход с телефона">
        <div>
            <h2>С телефона — прямо на объекте</h2>
            <p class="muted">Сервис ставится на телефон как приложение (Android и iPhone), без магазинов приложений — <a href="#install">как установить</a>.</p>
            <ul>
                <li>«Новый расход» и «Запись в журнал работ» — с ярлыка на главном экране.</li>
                <li>Фото чека сжимается перед отправкой — работает и на слабом мобильном интернете.</li>
                <li>Прораб видит только объекты и свои записи, без денег компании.</li>
            </ul>
        </div>
    </div>
</section>

@include('partials.install')

<section class="block">
    <div class="wrap">
        <h2>Как считаются деньги</h2>
        <p class="muted">Прогноз не бывает оптимистичнее реальности: пока статья в бюджете, считаем, что она израсходуется полностью; при перерасходе берём факт.</p>
        <table class="formula">
            <tr><td>Затраты факт</td><td>расходы + зарплата из журнала работ + налог (% от договора)</td></tr>
            <tr><td>Денежный результат</td><td>получено от заказчика − затраты факт</td></tr>
            <tr><td>Долг заказчика</td><td>сумма договора − получено</td></tr>
            <tr><td>Прогноз прибыли</td><td>сумма договора − Σ по статьям max(план, факт)</td></tr>
        </table>
    </div>
</section>

<section class="block">
    <div class="wrap">
        <div class="card cta">
            <h2>Бесплатно</h2>
            <p>Создайте организацию и пригласите сотрудников — по почте или ссылкой в мессенджер. Данные хранятся на сервере в России.</p>
            @auth
                <a class="btn" href="/app">Открыть учёт</a>
            @else
                @if ($open)
                    <a class="btn" href="/app/register">Зарегистрироваться</a>
                @else
                    <a class="btn" href="mailto:{{ config('app.support_email') }}?subject={{ rawurlencode('Учёт объектов: доступ') }}">Попросить доступ</a>
                @endif
            @endauth
        </div>
    </div>
</section>
@endsection
