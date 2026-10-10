<?php

namespace App\Filament\Salon\Resources\Reservations\Pages;

use App\Filament\Salon\CurrentSalon;
use App\Filament\Salon\Resources\Reservations\ReservationResource;
use App\Services\ReservationService;
use Carbon\CarbonImmutable;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Url;

class CreateReservation extends CreateRecord
{
    use ConfirmsOverlap;

    protected static string $resource = ReservationResource::class;

    /** Takvimden "Rezervasyon ekle" ile gelen gün (Y-m-d). */
    #[Url]
    public ?string $date = null;

    protected function afterFill(): void
    {
        if ($this->date && CarbonImmutable::canBeCreatedFromFormat($this->date, 'Y-m-d')) {
            $this->data['date'] = $this->date;
        }
    }

    protected function handleRecordCreation(array $data): Model
    {
        $this->haltIfOverlapping($data);

        return app(ReservationService::class)->save(CurrentSalon::get(), ReservationResource::serviceData($data));
    }

    protected function submitAfterOverlapConfirmed(): void
    {
        $this->create();
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
