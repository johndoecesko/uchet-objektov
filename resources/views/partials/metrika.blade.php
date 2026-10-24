{{--
    Яндекс Метрика — только на публичных страницах (главная, инструкция, документы) и только после «Принять» в окне о cookie.
    Внутри учёта, на страницах приглашения и регистрации её нет: там чужие данные и подписанные ссылки.
    До согласия и после отказа счётчик не загружается; «Настройки cookie» в подвале — изменить решение (cookie _ym* удаляются).
--}}
@if (($metrika = config('services.yandex_metrika.id')) && request()->routeIs('home', 'install', 'privacy', 'terms', 'consent'))
    <div id="cookie-note" hidden style="position: fixed; left: 16px; right: 16px; bottom: 16px; max-width: 640px; margin: 0 auto; z-index: 50; background: #fff; border: 1px solid var(--line); border-radius: 14px; box-shadow: 0 10px 30px rgba(15, 23, 42, .15); padding: 16px; font-size: 15px">
        <p style="margin: 0 0 12px">Можно мы будем считать посещения этих страниц с помощью Яндекс Метрики? Она ставит cookie и записывает действия на странице, чтобы мы видели, что удобно, а что нет. Внутри учёта статистика не собирается. <a href="{{ route('privacy') }}#cookie">Подробнее</a></p>
        <div style="display: flex; gap: 8px; flex-wrap: wrap">
            <button type="button" class="btn" data-consent="1">Принять</button>
            <button type="button" class="btn ghost" data-consent="0">Отказаться</button>
        </div>
    </div>
    <script>
        (function () {
            var KEY = 'cookie_consent', ID = {{ (int) $metrika }};
            function get() { try { return localStorage.getItem(KEY); } catch (e) { return null; } }
            function set(v) { try { localStorage.setItem(KEY, v); } catch (e) {} }
            function load() {
                (function (m, e, t, r, i, k, a) {
                    m[i] = m[i] || function () { (m[i].a = m[i].a || []).push(arguments); };
                    m[i].l = 1 * new Date();
                    for (var j = 0; j < document.scripts.length; j++) { if (document.scripts[j].src === r) { return; } }
                    k = e.createElement(t), a = e.getElementsByTagName(t)[0], k.async = 1, k.src = r, a.parentNode.insertBefore(k, a);
                })(window, document, 'script', 'https://mc.yandex.ru/metrika/tag.js?id=' + ID, 'ym');
                ym(ID, 'init', { ssr: true, webvisor: true, clickmap: true, ecommerce: 'dataLayer', referrer: document.referrer, url: location.href, accurateTrackBounce: true, trackLinks: true });
            }
            var note = document.getElementById('cookie-note');
            var v = get();
            if (v === '1') { load(); } else if (v === null) { note.hidden = false; }
            note.addEventListener('click', function (e) {
                var b = e.target.closest('[data-consent]');
                if (!b) { return; }
                set(b.getAttribute('data-consent'));
                note.hidden = true;
                if (b.getAttribute('data-consent') === '1') { load(); }
            });
            // «Настройки cookie»: забыть решение, удалить cookie Метрики и спросить заново
            window.cookieSettings = function () {
                try { localStorage.removeItem(KEY); } catch (e) {}
                document.cookie.split(';').forEach(function (c) {
                    var name = c.split('=')[0].trim();
                    if (name.indexOf('_ym') === 0) {
                        document.cookie = name + '=; Max-Age=0; path=/';
                        document.cookie = name + '=; Max-Age=0; path=/; domain=.' + location.hostname;
                    }
                });
                location.reload();
            };
        })();
    </script>
@endif
