<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;

/** Профиль пользователя: имя, почта, телефон, пароль. Новая почта вступает в силу после перехода по ссылке из письма на неё */
class EditProfile extends BaseEditProfile
{
    /**
     * Профиль живёт вне адреса организации (/app/profile), а меню организаций в шапке без текущей падает.
     * Берём организацию по умолчанию (последнюю открытую) — на каждом запросе Livewire, не только при открытии.
     * Организаций нет — сначала создать.
     */
    public function boot(): void
    {
        if (Filament::getTenant() !== null) {
            return;
        }

        $tenant = auth()->user()?->getDefaultTenant(Filament::getCurrentOrDefaultPanel());
        if ($tenant === null) {
            throw new HttpResponseException(new RedirectResponse(Filament::getTenantRegistrationUrl()));
        }

        Filament::setTenant($tenant, isQuiet: true);
    }

    protected function getEmailFormComponent(): Component
    {
        $field = parent::getEmailFormComponent();

        return filament()->hasEmailChangeVerification()
            ? $field->helperText('Почта — логин для входа. Новая почта начнёт работать после перехода по ссылке из письма на неё.')
            : $field->disabled()->dehydrated(false)->helperText('Почта — логин для входа.');
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            $this->getNameFormComponent(),
            $this->getEmailFormComponent(),
            TextInput::make('phone')->label('Телефон')->tel()->maxLength(50),
            $this->getPasswordFormComponent(),
            $this->getPasswordConfirmationFormComponent(),
            $this->getCurrentPasswordFormComponent(),
        ]);
    }
}
