<x-filament-panels::page>
    <style>
        .fp-about { display: grid; gap: 24px; max-width: 920px; }
        .fp-about h3 { font-weight: 600; margin: 0 0 4px; }
        .fp-about ol, .fp-about ul { margin: 0; padding-left: 22px; display: grid; gap: 8px; }
        .fp-about ol { list-style: decimal; }
        .fp-about ul { list-style: disc; }
        .fp-about a { color: var(--primary-600); text-decoration: underline; }
        .fp-about details { border-top: 1px solid rgba(127, 127, 127, .2); padding: 12px 0; }
        .fp-about details:first-of-type { border-top: 0; padding-top: 0; }
        .fp-about summary { cursor: pointer; font-weight: 600; }
        .fp-about details p { margin: 8px 0 0; opacity: .85; }
        .fp-about .muted { opacity: .7; font-size: 14px; }
        .fp-about .head { display: flex; gap: 16px; align-items: center; }
        .fp-about .head img { width: 56px; height: 56px; border-radius: 14px; }
        .fp-about .ver { display: inline-block; padding: 2px 10px; border-radius: 999px; background: rgba(37, 99, 235, .12); color: var(--primary-600); font-weight: 600; font-size: 14px; }
        .fp-about .release + .release { margin-top: 16px; }
    </style>

    <div class="fp-about">
        <x-filament::section>
            <div class="head">
                <img src="/icons/icon-192.png" alt="">
                <div>
                    <div style="font-size: 22px; font-weight: 700">Финпрораб</div>
                    <div class="muted">Учёт объектов — деньги по монтажным объектам: бюджет, расходы, зарплата, подотчёт, оплаты заказчика</div>
                    <div style="margin-top: 8px"><span class="ver">Версия {{ \App\Filament\Pages\About::version() }}</span> <span class="muted">от {{ \App\Filament\Pages\About::releasedAt() }}</span></div>
                </div>
            </div>
        </x-filament::section>

        <x-filament::section heading="С чего начать">
            <ol>
                @if ($isAdmin)
                    <li><b>Профиль организации</b> (меню организации слева вверху): название и ИНН.</li>
                    <li><b>Статьи затрат</b> уже заполнены — поправьте под себя в «Настройки → Статьи затрат».</li>
                    <li><b>Объект</b>: «Объекты → Создать» — заказчик (по ИНН подтянется сам), сумма договора, бюджет по статьям.</li>
                    <li><b>Сотрудники</b> со ставкой за день — для журнала работ и зарплаты.</li>
                    <li><b>Участники</b>: пригласите бухгалтера и прорабов — по почте или ссылкой в мессенджер.</li>
                    <li>Дальше каждый вносит своё: прораб — расходы и работы, офис — оплаты и выплаты. Итоги — на «Инфопанели» и в карточке объекта.</li>
                @else
                    <li><b>Расходы</b>: «Расходы → Создать» — объект, сумма, статья, фото чека.</li>
                    <li><b>Журнал работ</b>: кто, где и сколько дней работал.</li>
                    <li>Чеки удобнее отправлять через бота в MAX — см. ниже.</li>
                    <li>Установите сервис на телефон — так он открывается в одно касание.</li>
                @endif
            </ol>
        </x-filament::section>

        <x-filament::section heading="Частые вопросы">
            <details>
                <summary>Как установить на телефон?</summary>
                <p>Android — Chrome, меню ⋮ → «Установить приложение». iPhone — Safari, «Поделиться» → «На экран «Домой»». Подробно с картинками: <a href="{{ route('install') }}" target="_blank">инструкция</a>.</p>
            </details>
            <details>
                <summary>Как отправлять чеки через MAX?</summary>
                <p>Меню пользователя (кружок справа вверху) → «Подключить MAX» → отправьте код боту <a href="https://max.ru/id645317777929_bot" target="_blank">«БаZa»</a> в течение 10 минут. Дальше просто присылайте боту фото чека и отвечайте кнопками: объект, сумма, статья. Если вы в нескольких организациях, бот спросит, в какую.</p>
            </details>
            <details>
                <summary>Кто что видит?</summary>
                <p><b>Администратор</b> — всё, включая участников и настройки. <b>Менеджер / бухгалтер</b> — всю финансовую часть без настроек. <b>Прораб / инженер</b> — объекты и свои расходы и работы, без денег компании и чужих записей. Роль задаётся в каждой организации отдельно.</p>
            </details>
            <details>
                <summary>Как считается прогноз прибыли?</summary>
                <p>Сумма договора минус по каждой статье большее из «план» и «факт». Пока статья укладывается в бюджет, считаем, что она израсходуется полностью; при перерасходе берём факт. Прогноз не бывает оптимистичнее реальности.</p>
            </details>
            <details>
                <summary>Удалил запись по ошибке</summary>
                <p>Расходы, работы, поступления, выплаты и подотчёт сначала попадают в корзину: в списке откройте фильтр «Корзина», найдите запись и нажмите «Восстановить» (доступно администратору и бухгалтеру). Кто и что менял — в истории изменений на странице записи.</p>
            </details>
            <details>
                <summary>Как выгрузить данные в Excel?</summary>
                <p>В карточке объекта — кнопка «Excel»; отчёт собственнику за период — «Отчёт собственнику» в списке объектов; расчётный листок — в карточке сотрудника.</p>
            </details>
            <details>
                <summary>Забыл пароль / вхожу через Яндекс или VK</summary>
                <p>На странице входа — «Забыли свой пароль?», ссылка придёт на почту. Привязать или отвязать Яндекс ID и VK ID, задать пароль — в «Профиле».</p>
            </details>
        </x-filament::section>

        <x-filament::section heading="Что нового">
            @foreach ($releases as $r)
                <div class="release">
                    <h3>Версия {{ $r['version'] }} <span class="muted" style="font-weight: 400">— {{ $r['date'] }}</span></h3>
                    <ul>
                        @foreach ($r['items'] as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </x-filament::section>

        <x-filament::section heading="Связь и документы">
            <ul>
                <li>Вопросы, ошибки, предложения: Telegram <a href="https://t.me/{{ ltrim(config('app.support_telegram'), '@') }}" target="_blank">{{ config('app.support_telegram') }}</a>, почта <a href="mailto:{{ config('app.support_email') }}">{{ config('app.support_email') }}</a>.</li>
                <li><a href="{{ route('terms') }}" target="_blank">Пользовательское соглашение</a>, <a href="{{ route('privacy') }}" target="_blank">Политика обработки персональных данных</a>, <a href="{{ route('consent') }}" target="_blank">Согласие на обработку персональных данных</a>.</li>
                <li>Сервис бесплатный. Данные хранятся на сервере в России.</li>
                <li class="muted">© {{ date('Y') }} {{ config('legal.operator.short') ?: 'Финпрораб' }}</li>
            </ul>
        </x-filament::section>
    </div>
</x-filament-panels::page>
