{{-- Кнопки входа через Яндекс ID / VK ID под формой входа и регистрации. Без ключей — ничего не выводится --}}
@php($providers = \App\Support\Social\Provider::enabledAll())
@if (session('social_error'))
    <div style="margin-top: 16px; padding: 10px 12px; border-radius: 10px; background: #fef2f2; color: #991b1b; font-size: 14px">{{ session('social_error') }}</div>
@endif
@if ($providers)
    <div style="margin-top: 20px">
        <div style="display: flex; align-items: center; gap: 12px; color: #64748b; font-size: 14px; margin-bottom: 12px">
            <span style="flex: 1; height: 1px; background: #e2e8f0"></span>или<span style="flex: 1; height: 1px; background: #e2e8f0"></span>
        </div>
        <div style="display: grid; gap: 10px">
            @foreach ($providers as $p)
                <a href="{{ route('social.redirect', $p->name()) }}"
                   style="display: flex; align-items: center; justify-content: center; gap: 10px; padding: 10px 14px; border-radius: 10px; font-weight: 600; text-decoration: none; {{ $p->name() === 'yandex' ? 'background: #fc3f1d; color: #fff' : 'background: #0077ff; color: #fff' }}">
                    @if ($p->name() === 'yandex')
                        <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="12" fill="#fff"/><path d="M13.6 19h2.1V5h-3c-3.1 0-4.7 1.6-4.7 3.9 0 1.9.9 3 2.6 4.2L7.6 19h2.3l3.2-4.9-1.1-.7c-1.3-.9-2-1.6-2-3.1 0-1.3.9-2.2 2.6-2.2h1z" fill="#fc3f1d"/></svg>
                    @else
                        <svg width="20" height="20" viewBox="0 0 24 24" aria-hidden="true"><rect width="24" height="24" rx="7" fill="#fff"/><path d="M12.8 17c-4.4 0-6.9-3-7-8h2.2c.1 3.7 1.7 5.2 3 5.5V9h2.1v3.2c1.3-.1 2.6-1.6 3.1-3.2h2.1a6.2 6.2 0 0 1-2.8 4.1 6.4 6.4 0 0 1 3.3 3.9h-2.3a4 4 0 0 0-3.4-2.9V17z" fill="#0077ff"/></svg>
                    @endif
                    {{ $label ?? 'Войти' }} через {{ $p->label() }}
                </a>
            @endforeach
        </div>
    </div>
@endif
