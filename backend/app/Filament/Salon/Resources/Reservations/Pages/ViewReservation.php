<?php

namespace App\Filament\Salon\Resources\Reservations\Pages;

use App\Enums\ReservationStatus;
use App\Filament\Salon\CurrentSalon;
use App\Filament\Salon\Resources\Reservations\ReservationResource;
use App\Models\Reservation;
use App\Services\ReservationService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;

class ViewReservation extends ViewRecord
{
    protected static string $resource = ReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
            Action::make('cancel')
                ->label('İptal et')
                ->color('danger')
                ->icon('heroicon-o-x-circle')
                ->requiresConfirmation()
                ->modalHeading('Rezervasyonu iptal et')
                ->modalDescription('Etkinlik iptal edilir; gönderilmiş davetiyeler alıcılara "iptal edildi" olarak görünür.')
                ->visible(fn (Reservation $record) => $record->status !== ReservationStatus::Cancelled)
                ->action(function (Reservation $record) {
                    $data = ReservationResource::formDataFromRecord($record);
                    $data['status'] = ReservationStatus::Cancelled->value;

                    app(ReservationService::class)->save(CurrentSalon::get(), ReservationResource::serviceData($data), $record);

                    $this->refreshFormData([]);
                    Notification::make()->title('Rezervasyon iptal edildi')->success()->send();
                }),
        ];
    }
}
