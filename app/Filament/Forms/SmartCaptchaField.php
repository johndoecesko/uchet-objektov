<?php

namespace App\Filament\Forms;

use App\Support\SmartCaptcha;
use Filament\Forms\Components\Field;

/**
 * Виджет Яндекс SmartCaptcha в форме Filament: токен из виджета кладётся в состояние поля и проверяется на сервере.
 * Без ключей поле не показывается и не проверяется. После каждой попытки отправки форма шлёт событие
 * smart-captcha-reset — токен одноразовый.
 */
class SmartCaptchaField extends Field
{
    protected string $view = 'filament.forms.smart-captcha';

    public static function make(?string $name = 'captcha'): static
    {
        return parent::make($name);
    }

    protected function setUp(): void
    {
        parent::setUp();

        $this->hiddenLabel()
            ->visible(fn (): bool => SmartCaptcha::enabled())
            ->required()
            ->validationMessages(['required' => 'Подтвердите, что вы не робот.'])
            ->rule(SmartCaptcha::rule())
            ->dehydrated(false);
    }

    public function getSiteKey(): ?string
    {
        return SmartCaptcha::clientKey();
    }
}
