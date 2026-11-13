{{-- «Первые шаги» (App\Filament\Widgets\GettingStarted). Стили — свои: в собранном CSS Filament нет произвольных классов Tailwind --}}
@php
    $steps = $this->getSteps();
    $done = collect($steps)->where('done', true)->count();
@endphp
<x-filament-widgets::widget>
    <x-filament::section icon="heroicon-o-rocket-launch" :heading="'Первые шаги — сделано '.$done.' из '.count($steps)"
        description="Пара минут — и учёт начнёт считать за вас. Пример можно добавить и удалить одной кнопкой.">
        <x-slot name="afterHeader">{{ $this->hideAction }}</x-slot>

        <ol style="list-style: none; margin: 0; padding: 0; display: grid; gap: 10px">
            @foreach ($steps as $step)
                <li style="display: flex; gap: 10px; align-items: flex-start">
                    @if ($step['done'])
                        <x-filament::icon icon="heroicon-s-check-circle" style="width: 22px; height: 22px; color: rgb(22 163 74); flex: none" />
                    @else
                        <x-filament::icon icon="heroicon-o-ellipsis-horizontal-circle" style="width: 22px; height: 22px; color: rgb(156 163 175); flex: none" />
                    @endif
                    <div style="flex: 1; min-width: 0">
                        @if ($step['done'])
                            <span style="text-decoration: line-through; opacity: .65">{{ $step['label'] }}</span>
                        @elseif ($step['url'])
                            <x-filament::link :href="$step['url']" weight="semibold">{{ $step['label'] }}</x-filament::link>
                        @else
                            <span style="font-weight: 600">{{ $step['label'] }}</span>
                        @endif
                        @unless ($step['done'])
                            <div style="font-size: 13px; opacity: .7">{{ $step['hint'] }}</div>
                        @endunless
                    </div>
                    @if (! $step['done'] && $step['action'] === 'connectMax')
                        <div style="flex: none">{{ $this->connectMaxAction }}</div>
                    @endif
                </li>
            @endforeach
        </ol>

        @if ($this->fillExampleAction->isVisible() || $this->removeExampleAction->isVisible())
            <div style="display: flex; flex-wrap: wrap; gap: 8px 12px; align-items: center; margin-top: 16px; padding-top: 12px; border-top: 1px solid rgba(127,127,127,.2)">
                @if ($this->fillExampleAction->isVisible())
                    <span style="font-size: 14px; opacity: .75">Хотите сначала посмотреть на заполненный объект?</span>
                    {{ $this->fillExampleAction }}
                @else
                    <span style="font-size: 14px; opacity: .75">В учёте есть пример — объект и сотрудники «Пример — …».</span>
                    {{ $this->removeExampleAction }}
                @endif
            </div>
        @endif
    </x-filament::section>
</x-filament-widgets::widget>
