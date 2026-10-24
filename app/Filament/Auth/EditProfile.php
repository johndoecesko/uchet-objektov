<?php

namespace App\Filament\Auth;

use App\Support\Social\Provider;
use Filament\Actions\Action;
use Filament\Auth\Pages\EditProfile as BaseEditProfile;
use Filament\Facades\Filament;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\RedirectResponse;
use SensitiveParameter;

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

    public function mount(): void
    {
        parent::mount();

        // Итог привязки Яндекс ID / VK ID (SocialAuthController возвращает сюда)
        if ($message = session('social_status')) {
            Notification::make()->title($message)->success()->send();
        }
        if ($message = session('social_error')) {
            Notification::make()->title($message)->danger()->send();
        }
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
            $this->getSocialSection(),
        ]);
    }

    /** Учётка из соцсети: пароля нет — задать его можно без «текущего пароля» */
    protected function getPasswordFormComponent(): Component
    {
        $field = parent::getPasswordFormComponent();

        return $this->getUser()->has_password
            ? $field
            : $field->label('Задать пароль')->helperText('Вы входите через Яндекс ID или VK ID. Задайте пароль, чтобы входить и по почте.');
    }

    protected function getCurrentPasswordFormComponent(): Component
    {
        return $this->getUser()->has_password
            ? parent::getCurrentPasswordFormComponent()
            : TextInput::make('currentPassword')->hidden()->dehydrated(false);
    }

    protected function handleRecordUpdate(Model $record, #[SensitiveParameter] array $data): Model
    {
        if (filled($data['password'] ?? null)) {
            $record->forceFill(['has_password' => true]); // не в fillable — сохранится вместе с update()
        }

        return parent::handleRecordUpdate($record, $data);
    }

    /** Привязать/отвязать Яндекс ID и VK ID (только включённые). Последний способ входа отвязать нельзя */
    protected function getSocialSection(): Component
    {
        $providers = Provider::enabledAll();

        return Section::make('Вход через Яндекс ID и VK ID')
            ->visible($providers !== [])
            ->schema(collect($providers)->map(function (Provider $p) {
                $account = fn () => $this->getUser()->socialAccounts()->where('provider', $p->name())->first();

                return Actions::make([
                    Action::make('link_'.$p->name())
                        ->label('Привязать '.$p->label())
                        ->icon('heroicon-o-link')
                        ->color('gray')
                        ->url(route('social.redirect', ['provider' => $p->name(), 'link' => 1]))
                        ->visible(fn () => $account() === null),
                    Action::make('unlink_'.$p->name())
                        ->label(fn () => $p->label().' привязан'.(($e = $account()?->email) ? ' ('.$e.')' : '').' — отвязать')
                        ->icon('heroicon-o-x-mark')
                        ->color('gray')
                        ->visible(fn () => $account() !== null)
                        ->disabled(fn () => $this->getUser()->loginMethodsCount() <= 1)
                        ->tooltip(fn () => $this->getUser()->loginMethodsCount() <= 1 ? 'Это единственный способ входа — сначала задайте пароль' : null)
                        ->requiresConfirmation()
                        ->modalHeading('Отвязать '.$p->label().'?')
                        ->action(function () use ($account, $p) {
                            // повторная проверка на сервере: кнопку могли нажать в обход disabled
                            if ($this->getUser()->loginMethodsCount() <= 1) {
                                Notification::make()->title('Это единственный способ входа — сначала задайте пароль')->danger()->send();

                                return;
                            }
                            $account()?->delete();
                            Notification::make()->title($p->label().' отвязан')->success()->send();
                        }),
                ])->key('social_'.$p->name());
            })->values()->all());
    }
}
