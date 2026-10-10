<?php

namespace App\Filament\Salon\Resources\Reservations;

use App\Enums\EventType;
use App\Enums\ReservationStatus;
use App\Filament\Salon\CurrentSalon;
use App\Filament\Salon\Resources\Reservations\Pages\CreateReservation;
use App\Filament\Salon\Resources\Reservations\Pages\EditReservation;
use App\Filament\Salon\Resources\Reservations\Pages\ListReservations;
use App\Filament\Salon\Resources\Reservations\Pages\ViewReservation;
use App\Models\Event;
use App\Models\Reservation;
use App\Rules\Phone;
use App\Services\ReservationService;
use App\Services\RsvpStats;
use App\Support\Money;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Infolists\Components\ViewEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ReservationResource extends Resource
{
    protected static ?string $model = Reservation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentList;

    protected static ?string $modelLabel = 'rezervasyon';

    protected static ?string $pluralModelLabel = 'rezervasyonlar';

    protected static ?string $slug = 'rezervasyonlar';

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('salon_id', CurrentSalon::id())
            ->with(['event.invitation']);
    }

    public static function getRecordTitle($record): string
    {
        return $record?->event?->title ?? 'Rezervasyon';
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Etkinlik')
                ->columns(2)
                ->schema([
                    TextInput::make('title')
                        ->label('Başlık')
                        ->placeholder('Elif & Emre')
                        ->required()
                        ->maxLength(120),
                    Select::make('type')
                        ->label('Etkinlik türü')
                        ->options(collect(EventType::cases())->mapWithKeys(fn (EventType $t) => [$t->value => $t->label()]))
                        ->default(EventType::Wedding->value)
                        ->required(),
                    DatePicker::make('date')
                        ->label('Tarih')
                        ->required(),
                    Grid::make(2)->schema([
                        TimePicker::make('start_time')
                            ->label('Başlangıç')
                            ->seconds(false)
                            ->default('19:00')
                            ->required(),
                        TimePicker::make('end_time')
                            ->label('Bitiş')
                            ->helperText('Gece yarısını geçiyorsa ertesi gün sayılır.')
                            ->seconds(false)
                            ->default('23:00')
                            ->required(),
                    ]),
                    Select::make('status')
                        ->label('Durum')
                        ->options(collect(ReservationStatus::cases())->mapWithKeys(fn (ReservationStatus $s) => [$s->value => $s->label()]))
                        ->default(ReservationStatus::Option->value)
                        ->required(),
                ]),
            Section::make('Çift / iletişim')
                ->columns(2)
                ->schema([
                    TextInput::make('contact_name')
                        ->label('İletişim adı')
                        ->required()
                        ->maxLength(120),
                    TextInput::make('couple_phone')
                        ->label('Çiftin telefonu')
                        ->helperText('Çift uygulamaya bu numarayla girince etkinliği hazır bulur.')
                        ->tel()
                        ->placeholder('0532 111 22 33')
                        ->required()
                        ->rule(new Phone),
                ]),
            Section::make('Ücretler')
                ->columns(3)
                ->schema([
                    self::moneyInput('total_amount', 'Toplam ücret'),
                    self::moneyInput('deposit_amount', 'Kaparo'),
                    self::moneyInput('paid_amount', 'Ödenen'),
                    Textarea::make('notes')
                        ->label('Notlar')
                        ->rows(3)
                        ->columnSpanFull(),
                ]),
        ]);
    }

    private static function moneyInput(string $name, string $label): TextInput
    {
        return TextInput::make($name)
            ->label($label)
            ->numeric()
            ->minValue(0)
            ->maxValue(100_000_000)
            ->default(0)
            ->suffix('₺')
            ->required();
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort(fn (Builder $query) => $query->orderBy(
                Event::select('starts_at')->whereColumn('events.id', 'reservations.event_id'),
            ))
            ->columns([
                TextColumn::make('event.starts_at')
                    ->label('Tarih')
                    ->dateTime('d.m.Y H:i', ReservationService::DISPLAY_TIMEZONE),
                TextColumn::make('event.title')
                    ->label('Başlık')
                    ->searchable(),
                TextColumn::make('contact_name')
                    ->label('İletişim')
                    ->description(fn (Reservation $record) => $record->contact_phone)
                    ->searchable(['contact_name', 'contact_phone']),
                TextColumn::make('status')
                    ->label('Durum')
                    ->badge()
                    ->formatStateUsing(fn (ReservationStatus $state) => $state->label())
                    ->color(fn (ReservationStatus $state) => self::statusColor($state)),
                TextColumn::make('remaining')
                    ->label('Kalan alacak')
                    ->state(fn (Reservation $record) => Money::format($record->remainingAmount()))
                    ->alignEnd(),
                IconColumn::make('couple_linked')
                    ->label('Çift bağlandı')
                    ->state(fn (Reservation $record) => $record->event->owner_user_id !== null)
                    ->boolean(),
                IconColumn::make('invitation_sent')
                    ->label('Davetiye gönderildi')
                    ->state(fn (Reservation $record) => $record->event->invitation?->first_sent_at !== null)
                    ->boolean(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Durum')
                    ->options(collect(ReservationStatus::cases())->mapWithKeys(fn (ReservationStatus $s) => [$s->value => $s->label()])),
                TernaryFilter::make('upcoming')
                    ->label('Tarih')
                    ->placeholder('Tümü')
                    ->trueLabel('Yaklaşanlar')
                    ->falseLabel('Geçmiş')
                    ->queries(
                        true: fn (Builder $q) => $q->whereHas('event', fn ($e) => $e->where('starts_at', '>=', now()->startOfDay())),
                        false: fn (Builder $q) => $q->whereHas('event', fn ($e) => $e->where('starts_at', '<', now()->startOfDay())),
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Etkinlik')
                ->columns(3)
                ->schema([
                    TextEntry::make('event.title')->label('Başlık'),
                    TextEntry::make('event.type')->label('Tür')
                        ->formatStateUsing(fn (EventType $state) => $state->label()),
                    TextEntry::make('status')->label('Durum')
                        ->badge()
                        ->formatStateUsing(fn (ReservationStatus $state) => $state->label())
                        ->color(fn (ReservationStatus $state) => self::statusColor($state)),
                    TextEntry::make('event.starts_at')->label('Başlangıç')
                        ->dateTime('d.m.Y H:i', ReservationService::DISPLAY_TIMEZONE),
                    TextEntry::make('event.ends_at')->label('Bitiş')
                        ->dateTime('d.m.Y H:i', ReservationService::DISPLAY_TIMEZONE),
                    IconEntry::make('couple_linked')->label('Çift uygulamada')
                        ->state(fn (Reservation $record) => $record->event->owner_user_id !== null)
                        ->boolean(),
                ]),
            Section::make('İletişim ve ücretler')
                ->columns(3)
                ->schema([
                    TextEntry::make('contact_name')->label('İletişim adı'),
                    TextEntry::make('contact_phone')->label('Çiftin telefonu'),
                    TextEntry::make('notes')->label('Notlar')->placeholder('—'),
                    TextEntry::make('total_amount')->label('Toplam')
                        ->formatStateUsing(fn (int $state) => Money::format($state)),
                    TextEntry::make('paid_amount')->label('Ödenen')
                        ->formatStateUsing(fn (int $state) => Money::format($state)),
                    TextEntry::make('remaining')->label('Kalan alacak')
                        ->state(fn (Reservation $record) => Money::format($record->remainingAmount())),
                ]),
            Section::make('LCV özeti')
                ->schema([
                    ViewEntry::make('rsvp_summary')
                        ->hiddenLabel()
                        ->view('filament.salon.rsvp-summary')
                        ->state(fn (Reservation $record) => $record->event->share_headcount_with_salon
                            ? RsvpStats::forEvent($record->event)
                            : null),
                ]),
            Section::make('Çifti davet et')
                ->description('Çift uygulamayı indirip bu numarayla giriş yaptığında etkinliği hazır bulur.')
                ->visible(fn (Reservation $record) => $record->event->owner_user_id === null)
                ->schema([
                    ViewEntry::make('invite_couple')
                        ->hiddenLabel()
                        ->view('filament.salon.invite-couple'),
                ]),
        ]);
    }

    public static function statusColor(ReservationStatus $status): string
    {
        return match ($status) {
            ReservationStatus::Confirmed => 'success',
            ReservationStatus::Option => 'warning',
            ReservationStatus::Cancelled => 'gray',
        };
    }

    /**
     * Kayıttan form durumu (İstanbul saati, TL).
     *
     * @return array<string, mixed>
     */
    public static function formDataFromRecord(Reservation $record): array
    {
        $event = $record->event;
        $start = $event->starts_at->timezone(ReservationService::DISPLAY_TIMEZONE);
        $end = $event->ends_at?->timezone(ReservationService::DISPLAY_TIMEZONE);

        return [
            'title' => $event->title,
            'type' => $event->type->value,
            'date' => $start->toDateString(),
            'start_time' => $start->format('H:i'),
            'end_time' => $end?->format('H:i') ?? $start->format('H:i'),
            'status' => $record->status->value,
            'contact_name' => $record->contact_name,
            'couple_phone' => $record->contact_phone,
            'total_amount' => Money::toLira($record->total_amount),
            'deposit_amount' => Money::toLira($record->deposit_amount),
            'paid_amount' => Money::toLira($record->paid_amount),
            'notes' => $record->notes,
        ];
    }

    /**
     * Form durumundan servis verisi (tutarlar kuruş).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function serviceData(array $data): array
    {
        foreach (['total_amount', 'deposit_amount', 'paid_amount'] as $key) {
            $data[$key] = Money::toKurus($data[$key] ?? 0);
        }

        return $data;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListReservations::route('/'),
            'create' => CreateReservation::route('/create'),
            'view' => ViewReservation::route('/{record}'),
            'edit' => EditReservation::route('/{record}/edit'),
        ];
    }
}
