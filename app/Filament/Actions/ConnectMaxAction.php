<?php

namespace App\Filament\Actions;

use App\Support\Demo\Demo;
use App\Support\Max\MaxApi;
use App\Support\Max\MaxBot;
use Filament\Actions\Action;
use Filament\Notifications\Notification;

/**
 * «Подключить MAX» / «MAX подключён — отключить»: одноразовый код для привязки бота к учётной записи.
 * Видно, только если бот настроен; в демо — нет (привязка к общей учётке отдала бы бота любому гостю).
 * Используется в меню пользователя и в чек-листе «Первые шаги».
 */
class ConnectMaxAction
{
    public static function make(string $name = 'maxLink'): Action
    {
        return Action::make($name)
            ->label(fn () => auth()->user()?->max_user_id ? 'MAX подключён — отключить' : 'Подключить MAX')
            ->icon('heroicon-o-chat-bubble-left-right')
            ->visible(fn () => MaxApi::enabled() && ! Demo::active())
            ->requiresConfirmation(fn () => (bool) auth()->user()?->max_user_id)
            ->modalHeading('Отключить MAX?')
            ->modalDescription('Бот перестанет принимать от вас чеки, пока не подключите снова.')
            ->action(function () {
                abort_if(Demo::active(), 403, 'В демо можно только смотреть');
                $user = auth()->user();
                if ($user->max_user_id) {
                    $user->forceFill(['max_user_id' => null])->save();
                    Notification::make()->title('MAX отключён')->success()->send();

                    return;
                }
                Notification::make()
                    ->title('Код для MAX: '.MaxBot::linkCode($user))
                    ->body('Отправьте этот код боту в MAX в течение 10 минут. После этого присылайте боту фото чеков.')
                    ->persistent()
                    ->success()
                    ->send();
            });
    }
}
