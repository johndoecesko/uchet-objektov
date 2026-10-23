<?php

namespace App\Filament\Auth;

use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Schema;

/** Профиль пользователя: имя, телефон, пароль. Почта — логин, меняется только с подтверждением (когда включена почта) */
class EditProfile extends BaseEditProfile
{
    protected function getEmailFormComponent(): Component
    {
        $field = parent::getEmailFormComponent();

        return filament()->hasEmailChangeVerification()
            ? $field
            : $field->disabled()->dehydrated(false)->helperText('Почта — логин для входа. Сменить её можно будет после подключения почтовых уведомлений.');
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
