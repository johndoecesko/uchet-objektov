<?php

namespace App\Filament\Resources;

use App\Enums\UserRole;
use App\Filament\Resources\MemberResource\Pages;
use App\Models\Membership;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/** Участники текущей организации: роль и доступ. Аккаунт пользователя не удаляется — только убирается из организации */
class MemberResource extends Resource
{
    protected static ?string $model = Membership::class;

    protected static ?string $slug = 'members';

    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-user-circle';

    protected static string | \UnitEnum | null $navigationGroup = 'Настройки';

    protected static ?int $navigationSort = 2;

    protected static ?string $modelLabel = 'участника';

    protected static ?string $pluralModelLabel = 'Участники';

    protected static bool $hasTitleCaseModelLabel = false;

    /** Подсказка к роли; название роли прораба — из UserRole (на рабочем сайте оно своё) */
    public static function roleHint(): string
    {
        return UserRole::Foreman->getLabel().' видит объекты и вносит свои расходы и журнал работ; финансы не видит. '
            .UserRole::Manager->getLabel().' видит и ведёт всё, кроме настроек и участников. '
            .UserRole::Admin->getLabel().' — всё, включая участников.';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Grid::make(2)->columnSpanFull()->schema([
                TextEntry::make('user.name')->label('Имя'),
                TextEntry::make('user.email')->label('Email (логин)'),
                Select::make('role')->label('Роль в организации')->options(UserRole::class)->required()
                    ->helperText(self::roleHint()),
                Toggle::make('is_active')->label('Доступ к организации разрешён')->inline(false)
                    ->helperText('Выключите, чтобы временно закрыть доступ, не убирая из организации'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('avatar')->label('')->circular()->imageSize(32)
                    ->state(fn ($record) => filament()->getUserAvatarUrl($record->user)),
                TextColumn::make('user.name')->label('Имя')->searchable(),
                TextColumn::make('user.email')->label('Email')->searchable(),
                TextColumn::make('role')->label('Роль')->badge(),
                IconColumn::make('is_active')->label('Доступ')->boolean(),
                TextColumn::make('user.last_login_at')->label('Последний вход')->dateTime('d.m.Y H:i')->placeholder('—')->toggleable(),
                TextColumn::make('created_at')->label('В организации с')->date('d.m.Y')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at')
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()->label('Убрать')
                    ->modalHeading('Убрать из организации?')
                    ->modalDescription('Учётная запись останется, но доступа к этой организации у неё больше не будет. Записи, которые человек внёс, сохранятся.'),
            ]);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with('user');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListMembers::route('/'),
            'edit' => Pages\EditMember::route('/{record}/edit'),
        ];
    }
}
