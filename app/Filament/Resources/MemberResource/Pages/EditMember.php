<?php

namespace App\Filament\Resources\MemberResource\Pages;

use App\Enums\UserRole;
use App\Filament\Resources\MemberResource;
use App\Models\Membership;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditMember extends EditRecord
{
    protected static string $resource = MemberResource::class;

    public function getTitle(): string
    {
        return $this->getRecord()->user?->name ?? 'Участник';
    }

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()->label('Убрать из организации')];
    }

    /** В организации должен остаться хотя бы один администратор с доступом */
    protected function beforeSave(): void
    {
        $data = $this->form->getState();
        $stillAdmin = ($data['role'] ?? null) === UserRole::Admin || ($data['role'] ?? null) === UserRole::Admin->value;

        if ($this->getRecord()->role === UserRole::Admin && (! $stillAdmin || ! ($data['is_active'] ?? true))
            && ! Membership::otherActiveAdminExists($this->getRecord())) {
            Notification::make()->title('Нельзя: это единственный администратор организации')
                ->body('Сначала назначьте администратором другого участника.')->danger()->send();
            $this->halt();
        }
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
