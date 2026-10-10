<?php

namespace App\Filament\Admin\Resources\Events;

use App\Enums\EventCreator;
use App\Enums\EventType;
use App\Filament\Admin\Resources\Events\Pages\ListEvents;
use App\Models\Event;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class EventResource extends Resource
{
    protected static ?string $model = Event::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendar;

    protected static ?string $modelLabel = 'etkinlik';

    protected static ?string $pluralModelLabel = 'etkinlikler';

    protected static ?int $navigationSort = 4;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['owner', 'salon', 'invitation']))
            ->columns([
                TextColumn::make('starts_at')->label('Tarih')->dateTime('d.m.Y H:i', 'Europe/Istanbul')->sortable(),
                TextColumn::make('title')->label('Başlık')->searchable(),
                TextColumn::make('type')->label('Tür')->formatStateUsing(fn (EventType $state) => $state->label()),
                TextColumn::make('salon.name')->label('Salon')->placeholder('—'),
                TextColumn::make('owner.phone')->label('Sahip')->placeholder('Bağlanmadı'),
                IconColumn::make('pack')->label('Paket')->state(fn (Event $record) => $record->hasPack())->boolean(),
                IconColumn::make('sent')->label('Gönderildi')
                    ->state(fn (Event $record) => $record->invitation?->first_sent_at !== null)->boolean(),
                IconColumn::make('cancelled')->label('İptal')->state(fn (Event $record) => $record->isCancelled())->boolean(),
            ])
            ->filters([
                SelectFilter::make('type')->label('Tür')
                    ->options(collect(EventType::cases())->mapWithKeys(fn (EventType $t) => [$t->value => $t->label()])),
                SelectFilter::make('created_by')->label('Oluşturan')
                    ->options([EventCreator::User->value => 'Kullanıcı', EventCreator::Salon->value => 'Salon']),
                SelectFilter::make('salon_id')->label('Salon')->relationship('salon', 'name')->searchable(),
                TernaryFilter::make('pack_purchased_at')->label('Paket alındı')->nullable(),
                TernaryFilter::make('cancelled_at')->label('İptal')->nullable(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListEvents::route('/'),
        ];
    }
}
