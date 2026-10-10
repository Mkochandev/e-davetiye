<?php

namespace App\Filament\Salon\Widgets;

use App\Enums\ReservationStatus;
use App\Filament\Salon\CurrentSalon;
use App\Models\Reservation;
use App\Services\ReservationService;
use Carbon\CarbonImmutable;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class SalonStats extends StatsOverviewWidget
{
    protected static ?int $sort = 1;

    protected function getStats(): array
    {
        $now = CarbonImmutable::now(ReservationService::DISPLAY_TIMEZONE);

        return [
            Stat::make('Bugünkü etkinlik', $this->countBetween($now->startOfDay(), $now->endOfDay())),
            Stat::make('Bu haftaki etkinlik', $this->countBetween($now->startOfWeek(), $now->endOfWeek())),
            Stat::make('Bu ayın rezervasyonları', $this->countBetween($now->startOfMonth(), $now->endOfMonth())),
        ];
    }

    private function countBetween(CarbonImmutable $from, CarbonImmutable $to): int
    {
        return Reservation::query()
            ->where('salon_id', CurrentSalon::id())
            ->where('status', '!=', ReservationStatus::Cancelled)
            ->whereHas('event', fn ($q) => $q->whereBetween('starts_at', [$from->utc(), $to->utc()]))
            ->count();
    }
}
