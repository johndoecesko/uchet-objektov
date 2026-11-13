{{-- Плашка над страницами учёта в демо (AdminPanelProvider, BODY_START). Стили — свои: в собранном CSS Filament нет произвольных классов --}}
@php($open = \App\Support\Registration::open())
<div role="note" style="position: sticky; top: 0; z-index: 40; background: #fef3c7; border-bottom: 1px solid #fcd34d; color: #78350f; font-size: 14px; line-height: 1.4">
    <div style="max-width: 100%; margin: 0 auto; padding: 8px 16px; display: flex; flex-wrap: wrap; align-items: center; justify-content: center; gap: 8px 16px">
        <span><b>Это демо</b> — можно смотреть всё, изменить ничего нельзя. Данные обновляются каждую ночь.
            @if ($open) Зарегистрируйтесь, чтобы вести свой учёт. @else Регистрация по приглашению — <a href="mailto:{{ config('app.support_email') }}?subject={{ rawurlencode('Финпрораб: доступ') }}" style="color: inherit; text-decoration: underline">напишите нам</a>. @endif
        </span>
        <span style="display: inline-flex; gap: 8px">
            @if ($open)
                <form method="post" action="{{ route('demo.register') }}" style="margin: 0">@csrf
                    <button type="submit" style="background: #2563eb; color: #fff; border: 0; border-radius: 8px; padding: 5px 12px; font-weight: 600; cursor: pointer">Зарегистрироваться</button>
                </form>
            @endif
            <form method="post" action="{{ route('demo.exit') }}" style="margin: 0">@csrf
                <button type="submit" style="background: transparent; color: #78350f; border: 1px solid #d97706; border-radius: 8px; padding: 4px 12px; cursor: pointer">Выйти из демо</button>
            </form>
        </span>
    </div>
</div>
