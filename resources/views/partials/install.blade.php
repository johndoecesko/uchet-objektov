{{-- Как установить на телефон (PWA): на главной и на /install --}}
<style>
    .install { display: grid; grid-template-columns: 1fr 1fr; gap: 24px; }
    .install .card { display: grid; grid-template-columns: 150px 1fr; gap: 20px; align-items: start; }
    .install h3 { margin: 0 0 10px; font-size: 19px; }
    .install ol { margin: 0; padding-left: 20px; }
    .install li { margin-bottom: 10px; }
    .install svg.phone { width: 150px; height: auto; display: block; }
    .install .ic { display: inline-flex; vertical-align: middle; width: 26px; height: 26px; border: 1px solid var(--line); border-radius: 6px; align-items: center; justify-content: center; background: #fff; margin: 0 2px; }
    .install .hint { font-size: 14px; color: var(--muted); margin-top: 12px; }
    @media (max-width: 860px) { .install { grid-template-columns: 1fr; } }
    @media (max-width: 560px) { .install .card { grid-template-columns: 1fr; } .install svg.phone { margin: 0 auto; } }
</style>
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
