<?php

namespace App\Filament\Salon\Pages;

use App\Enums\ReservationStatus;
use App\Filament\Salon\CurrentSalon;
use App\Filament\Salon\Resources\Reservations\ReservationResource;
use App\Models\Reservation;
use App\Services\ReservationService;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Collection;
use Livewire\Attributes\Url;

/**
 * Aylık takvim: her gün kesin / opsiyon / boş; güne tıklayınca o günün rezervasyonları (spec §7).
 */
class ReservationCalendar extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $navigationLabel = 'Takvim';

    protected static ?string $title = 'Takvim';

    protected static ?string $slug = 'takvim';

    protected static ?int $navigationSort = 1;

    protected string $view = 'filament.salon.pages.reservation-calendar';

    /** Y-m */
    #[Url]
    public string $month = '';

    /** Y-m-d */
    #[Url]
    public ?string $day = null;

    public function mount(): void
    {
        if (! preg_match('/^\d{4}-\d{2}$/', $this->month)) {
            $this->month = $this->today()->format('Y-m');
        }
    }

    public function previousMonth(): void
    {
        $this->month = $this->monthStart()->subMonth()->format('Y-m');
        $this->day = null;
    }

    public function nextMonth(): void
    {
        $this->month = $this->monthStart()->addMonth()->format('Y-m');
        $this->day = null;
    }

    public function selectDay(string $day): void
    {
        $this->day = $day;
    }

    public function monthStart(): CarbonImmutable
    {
        return CarbonImmutable::createFromFormat('!Y-m', $this->month, ReservationService::DISPLAY_TIMEZONE);
    }

    private function today(): CarbonImmutable
    {
        return CarbonImmutable::now(ReservationService::DISPLAY_TIMEZONE);
    }

    /**
     * Pazartesiyle başlayan haftalar; her gün: date, in_month, is_today, confirmed, option.
     *
     * @return list<list<array<string, mixed>>>
     */
    public function weeks(): array
    {
        $start = $this->monthStart()->startOfWeek(CarbonImmutable::MONDAY);
        $end = $this->monthStart()->endOfMonth()->endOfWeek(CarbonImmutable::SUNDAY);

        $byDay = $this->reservationsBetween($start, $end)
            ->groupBy(fn (Reservation $r) => $this->localDate($r));

        $days = [];
        for ($date = $start; $date->lte($end); $date = $date->addDay()) {
            $key = $date->toDateString();
            $dayReservations = $byDay->get($key, collect());

            $days[] = [
                'date' => $key,
                'number' => $date->day,
                'in_month' => $date->month === $this->monthStart()->month,
                'is_today' => $date->isSameDay($this->today()),
                'confirmed' => $dayReservations->where('status', ReservationStatus::Confirmed)->count(),
                'option' => $dayReservations->where('status', ReservationStatus::Option)->count(),
            ];
        }

        return array_chunk($days, 7);
    }

    /**
     * @return Collection<int, Reservation>
     */
    public function dayReservations(): Collection
    {
        if (! $this->day || ! CarbonImmutable::canBeCreatedFromFormat($this->day, 'Y-m-d')) {
            return collect();
        }

        $day = CarbonImmutable::createFromFormat('!Y-m-d', $this->day, ReservationService::DISPLAY_TIMEZONE);

        return $this->reservationsBetween($day, $day)->values();
    }

    /**
     * @return Collection<int, Reservation>
     */
    private function reservationsBetween(CarbonImmutable $from, CarbonImmutable $to): Collection
    {
        return Reservation::query()
            ->where('salon_id', CurrentSalon::id())
            ->with('event')
            ->whereHas('event', fn ($q) => $q->whereBetween('starts_at', [
                $from->startOfDay()->utc(),
                $to->endOfDay()->utc(),
            ]))
            ->get()
            ->sortBy(fn (Reservation $r) => $r->event->starts_at);
    }

    private function localDate(Reservation $reservation): string
    {
        return $reservation->event->starts_at->timezone(ReservationService::DISPLAY_TIMEZONE)->toDateString();
    }

    public function createUrl(string $day): string
    {
        return ReservationResource::getUrl('create', ['date' => $day]);
    }

    public function viewUrl(Reservation $reservation): string
    {
        return ReservationResource::getUrl('view', ['record' => $reservation]);
    }
}
