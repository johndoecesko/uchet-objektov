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
    .install { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
    .install .card { display: grid; grid-template-columns: 150px 1fr; gap: 20px; align-items: start; }
    .install h3 { margin: 0 0 10px; font-size: 19px; }
    .install ol { margin: 0; padding-left: 20px; }
    .install li { margin-bottom: 10px; }
    .install svg.phone { width: 150px; height: auto; display: block; }
    .install .ic { display: inline-flex; vertical-align: middle; width: 26px; height: 26px; border: 1px solid var(--line); border-radius: 6px; align-items: center; justify-content: center; background: #fff; margin: 0 2px; }
    .install .hint { font-size: 14px; color: var(--muted); margin-top: 12px; }
    @media (max-width: 860px) {
        .install { grid-template-columns: 1fr; }
        .hero { grid-template-columns: 1fr; padding-top: 32px; gap: 24px; }
        .hero h1 { font-size: 30px; }
        .grid { grid-template-columns: 1fr 1fr; }
        .shots { grid-template-columns: 1fr; }
        .mobile { grid-template-columns: 1fr; }
        .mobile img { max-width: 260px; margin: 0 auto; }
    }
    @media (max-width: 560px) {
        .install .card { grid-template-columns: 1fr; }
        .install svg.phone { margin: 0 auto; }
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

<section class="block" id="install">
    <div class="wrap">
        <h2>Как установить на телефон</h2>
        <p class="muted">Одна минута, без магазина приложений. После установки на главном экране появится значок «Учёт» — открывается сразу, без адресной строки, как обычное приложение.</p>
        <div class="install">
            <div class="card">
                {{-- Android: меню ⋮ в Chrome → «Установить приложение» --}}
                <svg class="phone" viewBox="0 0 150 280" role="img" aria-label="Android, Chrome: меню с тремя точками, пункт «Установить приложение»">
                    <rect x="2" y="2" width="146" height="276" rx="20" fill="#fff" stroke="#0f172a" stroke-width="3"/>
                    <rect x="14" y="24" width="122" height="22" rx="11" fill="#f1f5f9"/>
                    <text x="24" y="39" font-size="10" fill="#475569" font-family="system-ui, sans-serif">finprorab.ru</text>
                    <circle cx="130" cy="35" r="11" fill="#dbeafe" stroke="#2563eb" stroke-width="2"/>
                    <circle cx="130" cy="30" r="1.6" fill="#0f172a"/><circle cx="130" cy="35" r="1.6" fill="#0f172a"/><circle cx="130" cy="40" r="1.6" fill="#0f172a"/>
                    <rect x="48" y="52" width="94" height="104" rx="8" fill="#fff" stroke="#cbd5e1"/>
                    <rect x="56" y="62" width="70" height="7" rx="3.5" fill="#e2e8f0"/>
                    <rect x="56" y="80" width="60" height="7" rx="3.5" fill="#e2e8f0"/>
                    <rect x="52" y="94" width="86" height="24" rx="6" fill="#dbeafe" stroke="#2563eb" stroke-width="1.5"/>
                    <text x="57" y="104" font-size="8.5" fill="#1d4ed8" font-weight="600" font-family="system-ui, sans-serif">Установить</text>
                    <text x="57" y="114" font-size="8.5" fill="#1d4ed8" font-weight="600" font-family="system-ui, sans-serif">приложение</text>
                    <rect x="56" y="128" width="66" height="7" rx="3.5" fill="#e2e8f0"/>
                    <rect x="56" y="142" width="50" height="7" rx="3.5" fill="#e2e8f0"/>
                    <rect x="14" y="170" width="122" height="10" rx="5" fill="#e2e8f0"/>
                    <rect x="14" y="188" width="90" height="10" rx="5" fill="#e2e8f0"/>
                    <rect x="14" y="206" width="110" height="10" rx="5" fill="#e2e8f0"/>
                    <rect x="55" y="262" width="40" height="4" rx="2" fill="#0f172a"/>
                </svg>
                <div>
                    <h3>Android</h3>
                    <ol>
                        <li>Откройте <b>finprorab.ru</b> в браузере <b>Chrome</b> и войдите.</li>
                        <li>Нажмите меню <span class="ic" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 14 14"><circle cx="7" cy="2.5" r="1.4"/><circle cx="7" cy="7" r="1.4"/><circle cx="7" cy="11.5" r="1.4"/></svg></span> справа вверху.</li>
                        <li>Выберите <b>«Установить приложение»</b> (или «Добавить на главный экран») и подтвердите.</li>
                    </ol>
                    <p class="hint">В Яндекс Браузере — меню <span class="ic" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 14 14"><circle cx="7" cy="2.5" r="1.4"/><circle cx="7" cy="7" r="1.4"/><circle cx="7" cy="11.5" r="1.4"/></svg></span> → «Добавить ярлык» или «Установить».</p>
                </div>
            </div>
            <div class="card">
                {{-- iPhone: «Поделиться» в Safari → «На экран «Домой»» --}}
                <svg class="phone" viewBox="0 0 150 280" role="img" aria-label="iPhone, Safari: кнопка «Поделиться», пункт «На экран Домой»">
                    <rect x="2" y="2" width="146" height="276" rx="24" fill="#fff" stroke="#0f172a" stroke-width="3"/>
                    <rect x="52" y="10" width="46" height="10" rx="5" fill="#0f172a"/>
                    <rect x="14" y="30" width="122" height="10" rx="5" fill="#e2e8f0"/>
                    <rect x="14" y="48" width="90" height="10" rx="5" fill="#e2e8f0"/>
                    <rect x="10" y="118" width="130" height="104" rx="12" fill="#f8fafc" stroke="#cbd5e1"/>
                    <rect x="18" y="128" width="80" height="7" rx="3.5" fill="#e2e8f0"/>
                    <rect x="18" y="144" width="96" height="7" rx="3.5" fill="#e2e8f0"/>
                    <rect x="14" y="158" width="122" height="26" rx="6" fill="#dbeafe" stroke="#2563eb" stroke-width="1.5"/>
                    <text x="20" y="175" font-size="9" fill="#1d4ed8" font-weight="600" font-family="system-ui, sans-serif">На экран «Домой»</text>
                    <rect x="115" y="164" width="14" height="14" rx="3" fill="none" stroke="#1d4ed8" stroke-width="1.5"/>
                    <path d="M122 167v8M118 171h8" stroke="#1d4ed8" stroke-width="1.5"/>
                    <rect x="18" y="194" width="70" height="7" rx="3.5" fill="#e2e8f0"/>
                    <rect x="18" y="208" width="90" height="7" rx="3.5" fill="#e2e8f0"/>
                    <rect x="10" y="232" width="130" height="34" rx="10" fill="#f1f5f9"/>
                    <circle cx="75" cy="249" r="13" fill="#dbeafe" stroke="#2563eb" stroke-width="2"/>
                    <path d="M70 248v7h10v-7" fill="none" stroke="#0f172a" stroke-width="1.6" stroke-linejoin="round"/>
                    <path d="M75 252v-11M71.5 244.5 75 241l3.5 3.5" fill="none" stroke="#0f172a" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                <div>
                    <h3>iPhone</h3>
                    <ol>
                        <li>Откройте <b>finprorab.ru</b> в <b>Safari</b> и войдите.</li>
                        <li>Нажмите <b>«Поделиться»</b> <span class="ic" aria-hidden="true"><svg width="14" height="16" viewBox="0 0 14 16" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M3 7v7h8V7"/><path d="M7 10V1M4 4l3-3 3 3"/></svg></span> внизу экрана.</li>
                        <li>Пролистайте и выберите <b>«На экран «Домой»»</b> <span class="ic" aria-hidden="true"><svg width="14" height="14" viewBox="0 0 14 14" fill="none" stroke="currentColor" stroke-width="1.5"><rect x="1" y="1" width="12" height="12" rx="3"/><path d="M7 4v6M4 7h6"/></svg></span>, затем <b>«Добавить»</b>.</li>
                    </ol>
                    <p class="hint">Установка на iPhone работает только из Safari. Если сайт открыт в другом браузере — скопируйте адрес в Safari.</p>
                </div>
            </div>
        </div>
    </div>
</section>

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
