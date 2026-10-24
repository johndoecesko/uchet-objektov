<?php

namespace App\Filament\Pages\Tenancy;

use App\Models\Organization;
use App\Support\Avatar;
use App\Support\DaData;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Pages\Tenancy\EditTenantProfile;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;

/** Профиль организации (только администратор организации): название и ИНН */
class EditOrganizationProfile extends EditTenantProfile
{
    public static function getLabel(): string
    {
        return 'Профиль организации';
    }

    public static function canView(Model $tenant): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    /** Поля организации — общие для создания и профиля */
    public static function organizationFields(): array
    {
        return [
            Select::make('inn_lookup')
                ->label('Найти по ИНН или названию')
                ->placeholder('Начните вводить ИНН или название')
                ->searchable()
                ->getSearchResultsUsing(fn (string $search): array => DaData::parties($search))
                ->getOptionLabelUsing(fn ($value): ?string => DaData::party($value)['name'] ?? $value)
                ->noSearchResultsMessage('Ничего не найдено')
                ->helperText('Заполнит название и ИНН — их можно поправить')
                ->live()
                ->afterStateUpdated(function ($state, Set $set) {
                    if ($p = DaData::party($state)) {
                        $set('name', $p['name']);
                        $set('inn', $p['inn']);
                    }
                })
                ->dehydrated(false)
                ->visible(fn () => DaData::enabled()),
            TextInput::make('name')->label('Название организации')->required()->maxLength(255)
                ->placeholder('Например: ООО «Монтаж-Сервис» или ИП Иванов'),
            TextInput::make('inn')->label('ИНН (необязательно)')->maxLength(12)
                ->rule('regex:/^(\d{10}|\d{12})$/')
                ->validationMessages(['regex' => 'ИНН — 10 цифр (организация) или 12 (ИП)']),
        ];
    }

    public function form(Schema $schema): Schema
    {
        return $schema->components([
            Avatar::field('logo_path', 'logos', 'Логотип')->helperText('Виден в переключателе организаций слева вверху. JPG, PNG или WebP до 5 МБ.'),
            ...self::organizationFields(),
            TextEntry::make('slug')->label('Адрес в учёте')
                ->state(fn (?Organization $record) => $record ? url('/app/'.$record->slug) : null),
        ]);
    }
}
