@php
    use App\Enums\ReservationStatus;
    use App\Services\ReservationService;

    $monthLabel = $this->monthStart()->locale('tr')->translatedFormat('F Y');
    $dayReservations = $this->dayReservations();
@endphp

<x-filament-panels::page>
    <style>
        .zc-grid { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 4px; }
        .zc-head { font-size: .75rem; font-weight: 600; opacity: .6; text-align: center; padding: .25rem 0; }
        .zc-day { min-height: 72px; border: 1px solid rgba(127,127,127,.2); border-radius: 10px; padding: .375rem; text-align: left; display: flex; flex-direction: column; gap: 4px; cursor: pointer; background: transparent; }
        .zc-day:hover { border-color: rgba(138,31,61,.5); }
        .zc-out { opacity: .4; }
        .zc-today .zc-num { color: #8A1F3D; font-weight: 700; }
        .zc-selected { border-color: #8A1F3D; box-shadow: 0 0 0 1px #8A1F3D; }
        .zc-num { font-size: .875rem; }
        .zc-chip { font-size: .6875rem; border-radius: 999px; padding: 1px 6px; width: fit-content; }
        .zc-confirmed { background: #E3F0E8; color: #23603F; }
        .zc-option { background: #FDF0D5; color: #8A5A00; }
        .zc-row { display: flex; justify-content: space-between; align-items: center; gap: 1rem; padding: .625rem 0; border-bottom: 1px solid rgba(127,127,127,.15); }
        @media (max-width: 640px) { .zc-day { min-height: 52px; } .zc-chip { font-size: .625rem; padding: 0 4px; } }
    </style>

    <x-filament::section>
        <div style="display:flex; justify-content:space-between; align-items:center; margin-bottom:1rem;">
            <x-filament::icon-button icon="heroicon-o-chevron-left" wire:click="previousMonth" label="Önceki ay" />
            <h2 style="font-size:1.125rem; font-weight:600; text-transform:capitalize;">{{ $monthLabel }}</h2>
            <x-filament::icon-button icon="heroicon-o-chevron-right" wire:click="nextMonth" label="Sonraki ay" />
        </div>

        <div class="zc-grid">
            @foreach (['Pzt', 'Sal', 'Çar', 'Per', 'Cum', 'Cmt', 'Paz'] as $weekday)
                <div class="zc-head">{{ $weekday }}</div>
            @endforeach

            @foreach ($this->weeks() as $week)
                @foreach ($week as $day)
                    <button
                        type="button"
                        wire:click="selectDay('{{ $day['date'] }}')"
                        @class([
                            'zc-day',
                            'zc-out' => ! $day['in_month'],
                            'zc-today' => $day['is_today'],
                            'zc-selected' => $this->day === $day['date'],
                        ])
                    >
                        <span class="zc-num">{{ $day['number'] }}</span>
                        @if ($day['confirmed'])
                            <span class="zc-chip zc-confirmed">kesin{{ $day['confirmed'] > 1 ? ' ×'.$day['confirmed'] : '' }}</span>
                        @endif
                        @if ($day['option'])
                            <span class="zc-chip zc-option">opsiyon{{ $day['option'] > 1 ? ' ×'.$day['option'] : '' }}</span>
                        @endif
                    </button>
                @endforeach
            @endforeach
        </div>
    </x-filament::section>

    @if ($this->day)
        <x-filament::section>
            <x-slot name="heading">
                {{ \Carbon\CarbonImmutable::parse($this->day)->locale('tr')->translatedFormat('j F Y, l') }}
            </x-slot>
            <x-slot name="afterHeader">
                <x-filament::button tag="a" :href="$this->createUrl($this->day)" icon="heroicon-o-plus" size="sm">
                    Rezervasyon ekle
                </x-filament::button>
            </x-slot>

            @forelse ($dayReservations as $reservation)
                <div class="zc-row">
                    <div>
                        <x-filament::link :href="$this->viewUrl($reservation)">{{ $reservation->event->title }}</x-filament::link>
                        <div style="font-size:.8125rem; opacity:.7;">
                            {{ $reservation->event->starts_at->timezone(ReservationService::DISPLAY_TIMEZONE)->format('H:i') }}–{{ $reservation->event->ends_at?->timezone(ReservationService::DISPLAY_TIMEZONE)->format('H:i') }}
                            · {{ $reservation->contact_name }}
                        </div>
                    </div>
                    <x-filament::badge :color="\App\Filament\Salon\Resources\Reservations\ReservationResource::statusColor($reservation->status)">
                        {{ $reservation->status->label() }}
                    </x-filament::badge>
                </div>
            @empty
                <p style="opacity:.7;">Bu gün için rezervasyon yok.</p>
            @endforelse
        </x-filament::section>
    @endif
</x-filament-panels::page>
