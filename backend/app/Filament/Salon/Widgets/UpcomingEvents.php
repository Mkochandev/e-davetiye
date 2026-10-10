<?php

namespace App\Filament\Salon\Widgets;

use App\Enums\ReservationStatus;
use App\Filament\Salon\CurrentSalon;
use App\Filament\Salon\Resources\Reservations\ReservationResource;
use App\Models\Event;
use App\Models\Reservation;
use App\Services\ReservationService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;

class UpcomingEvents extends TableWidget
{
    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Yaklaşan etkinlikler';

    public function table(Table $table): Table
    {
        return $table
            ->query(fn () => Reservation::query()
                ->where('salon_id', CurrentSalon::id())
                ->where('status', '!=', ReservationStatus::Cancelled)
                ->whereHas('event', fn ($q) => $q->where('starts_at', '>=', now()))
                ->with('event.invitation')
                ->orderBy(Event::select('starts_at')->whereColumn('events.id', 'reservations.event_id'))
                ->limit(5))
            ->paginated(false)
            ->recordUrl(fn (Reservation $record) => ReservationResource::getUrl('view', ['record' => $record]))
            ->columns([
                TextColumn::make('event.starts_at')
                    ->label('Tarih')
                    ->dateTime('d.m.Y H:i', ReservationService::DISPLAY_TIMEZONE),
                TextColumn::make('event.title')->label('Başlık'),
                TextColumn::make('status')
                    ->label('Durum')
                    ->badge()
                    ->formatStateUsing(fn (ReservationStatus $state) => $state->label())
                    ->color(fn (ReservationStatus $state) => ReservationResource::statusColor($state)),
                TextColumn::make('invitation')
                    ->label('Davetiye')
                    ->badge()
                    ->state(fn (Reservation $record) => $record->event->invitation?->first_sent_at ? 'Gönderildi' : 'Gönderilmedi')
                    ->color(fn (string $state) => $state === 'Gönderildi' ? 'success' : 'gray'),
            ]);
    }
}
