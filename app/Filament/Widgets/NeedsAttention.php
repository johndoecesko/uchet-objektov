<?php

namespace App\Filament\Widgets;

use App\Filament\Support\Fields;
use App\Support\Attention;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class NeedsAttention extends TableWidget
{
    protected static bool $isLazy = false;

    protected static ?int $sort = 2;

    protected int | string | array $columnSpan = 'full';

    public static function canView(): bool
    {
        return Fields::isOffice();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Требует внимания')
            ->records(fn (): array => Attention::items()->mapWithKeys(fn ($i, $k) => ['a'.$k => $i])->all())
            ->columns([
                TextColumn::make('level')->label('')
                    ->formatStateUsing(fn ($state) => $state === 'danger' ? 'Срочно' : 'Проверить')
                    ->badge()
                    ->color(fn ($state) => $state),
                TextColumn::make('title')->label('Объект / сотрудник')->weight('bold')->wrap(),
                TextColumn::make('text')->label('Что случилось')->wrap(),
            ])
            ->recordUrl(fn (array $record) => $record['url'])
            ->emptyStateHeading('Всё в порядке')
            ->emptyStateDescription('Перерасходов, низкой маржи и незакрытого подотчёта нет.')
            ->emptyStateIcon('heroicon-o-check-circle')
            ->paginated(false);
    }
}
