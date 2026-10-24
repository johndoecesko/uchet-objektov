<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    {{-- Скрипт капчи грузится только на этой странице. Несколько виджетов на странице ждут загрузки в общей очереди --}}
    <div
        wire:ignore
        x-data="{
            state: $wire.$entangle(@js($getStatePath())),
            widget: null,
            init() {
                const render = () => {
                    this.widget = window.smartCaptcha.render(this.$refs.box, {
                        sitekey: @js($getSiteKey()),
                        hl: 'ru',
                        callback: (token) => { this.state = token },
                    });
                    window.smartCaptcha.subscribe(this.widget, 'token-expired', () => { this.state = null });
                };
                if (window.smartCaptcha) { render(); return; }
                window.__smartCaptchaQueue = window.__smartCaptchaQueue || [];
                window.__smartCaptchaQueue.push(render);
                if (! document.getElementById('smartcaptcha-js')) {
                    window.__smartCaptchaReady = () => window.__smartCaptchaQueue.splice(0).forEach((f) => f());
                    const script = document.createElement('script');
                    script.id = 'smartcaptcha-js';
                    script.src = 'https://smartcaptcha.cloud.yandex.ru/captcha.js?render=onload&onload=__smartCaptchaReady';
                    script.defer = true;
                    document.head.appendChild(script);
                }
            },
        }"
        x-on:smart-captcha-reset.window="if (widget !== null) { window.smartCaptcha.reset(widget); state = null }"
    >
        <div x-ref="box" style="min-height: 102px"></div>
    </div>
</x-dynamic-component>
