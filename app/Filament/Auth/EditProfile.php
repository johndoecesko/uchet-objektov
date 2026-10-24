<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;

/** Профиль пользователя: имя, почта, телефон, пароль. Новая почта вступает в силу после перехода по ссылке из письма на неё */
class EditProfile extends BaseEditProfile
{
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
