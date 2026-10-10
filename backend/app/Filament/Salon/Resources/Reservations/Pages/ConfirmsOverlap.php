<?php

namespace App\Filament\Salon\Resources\Reservations\Pages;

use App\Enums\ReservationStatus;
use App\Filament\Salon\CurrentSalon;
use App\Models\Reservation;
use App\Services\ReservationService;
use Filament\Actions\Action;

/**
 * Aynı salonda saatleri çakışan kesin rezervasyon varsa kaydetmeden önce uyarı ve onay (spec §7).
 */
trait ConfirmsOverlap
{
    public bool $overlapConfirmed = false;

    public ?string $overlapMessage = null;

    abstract protected function submitAfterOverlapConfirmed(): void;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function haltIfOverlapping(array $data, ?Reservation $except = null): void
    {
        if ($this->overlapConfirmed || $data['status'] === ReservationStatus::Cancelled->value) {
            return;
        }

        [$startsAt, $endsAt] = ReservationService::period($data['date'], $data['start_time'], $data['end_time']);

        $conflicts = app(ReservationService::class)->overlapping(CurrentSalon::get(), $startsAt, $endsAt, $except);

        if ($conflicts->isEmpty()) {
            return;
        }

        $this->overlapMessage = 'Bu saatlerde kesin rezervasyon var: '.$conflicts
            ->map(fn (Reservation $r) => $r->event->title.' ('
                .$r->event->starts_at->timezone(ReservationService::DISPLAY_TIMEZONE)->format('d.m.Y H:i').'–'
                .$r->event->ends_at->timezone(ReservationService::DISPLAY_TIMEZONE)->format('H:i').')')
            ->implode(', ').'. Yine de kaydetmek istiyor musunuz?';

        $this->mountAction('confirmOverlap');
        $this->halt();
    }

    public function confirmOverlapAction(): Action
    {
        return Action::make('confirmOverlap')
            ->requiresConfirmation()
            ->modalIcon('heroicon-o-exclamation-triangle')
            ->modalHeading('Saat çakışması')
            ->modalDescription(fn () => $this->overlapMessage)
            ->modalSubmitActionLabel('Yine de kaydet')
            ->color('warning')
            ->action(function () {
                $this->overlapConfirmed = true;
                $this->submitAfterOverlapConfirmed();
            });
    }
}
