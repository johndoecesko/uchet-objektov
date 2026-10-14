<?php

namespace App\Filament\Pages;

use App\Models\Setting;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/** Настройки (только админ): пороги блока «Требует внимания» */
class Settings extends Page
{
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-adjustments-horizontal';

    protected static string | \UnitEnum | null $navigationGroup = 'Настройки';

    protected static ?int $navigationSort = 4;

    protected static ?string $navigationLabel = 'Параметры';

    protected static ?string $title = 'Параметры';

    protected static ?string $slug = 'settings';

    /** @var array<string, mixed> */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isAdmin();
    }

    public function mount(): void
    {
        $this->form->fill([
            'advance_days' => (int) Setting::get('attention.advance_days'),
            'low_margin' => (float) Setting::get('attention.low_margin'),
        ]);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make('Требует внимания')
                    ->description('Когда объект или сотрудник попадает в блок «Требует внимания» на главной')
                    ->columns(2)
                    ->schema([
                        TextInput::make('advance_days')
                            ->label('Подотчёт без отчёта, дней')
                            ->helperText('Сотрудник с остатком подотчёта, если с последней выдачи прошло столько дней')
                            ->integer()->minValue(1)->maxValue(365)->required()
                            ->suffix('дн.'),
                        TextInput::make('low_margin')
                            ->label('Низкая рентабельность, %')
                            ->helperText('Предупреждение, если прогноз прибыли ниже этой доли от договора')
                            ->numeric()->minValue(0)->maxValue(100)->step(0.5)->required()
                            ->suffix('%'),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([Actions::make([Action::make('save')->label('Сохранить')->submit('save')])->key('form-actions')]),
        ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);
        $data = $this->form->getState();
        Setting::put('attention.advance_days', (int) $data['advance_days']);
        Setting::put('attention.low_margin', round((float) $data['low_margin'], 1));

        Notification::make()->title('Сохранено')->success()->send();
    }
}
