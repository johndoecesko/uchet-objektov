<?php

namespace App\Filament\Resources;

use App\Enums\UserRole;
use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-user-circle';

    protected static string | \UnitEnum | null $navigationGroup = 'Настройки';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'пользователя';

    protected static ?string $pluralModelLabel = 'Пользователи';

    protected static bool $hasTitleCaseModelLabel = false;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->columnSpanFull()->schema([
                TextInput::make('name')->label('Имя')->required()->maxLength(255),
                TextInput::make('email')->label('Email (логин)')->email()->required()->unique(ignoreRecord: true),
                TextInput::make('phone')->label('Телефон')->tel()->maxLength(50),
                Select::make('role')->label('Роль')->options(UserRole::class)->default(UserRole::Foreman)->required()
                    ->helperText('Прораб / монтажник видит объекты и вносит свои расходы и журнал работ; финансы не видит.'),
                TextInput::make('password')->label('Пароль')->password()->revealable()
                    ->minLength(8)
                    ->required(fn (string $operation) => $operation === 'create')
                    ->dehydrated(fn (?string $state) => filled($state))
                    ->helperText(fn (string $operation) => $operation === 'edit' ? 'Оставьте пустым, чтобы не менять' : null),
                Toggle::make('is_active')->label('Доступ разрешён')->default(true)->inline(false),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Имя')->searchable(),
                TextColumn::make('email')->label('Email')->searchable(),
                TextColumn::make('role')->label('Роль')->badge(),
                IconColumn::make('is_active')->label('Доступ')->boolean(),
                TextColumn::make('updated_at')->label('Изменён')->dateTime('d.m.Y H:i')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->recordActions([EditAction::make()]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
