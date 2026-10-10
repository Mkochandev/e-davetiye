<?php

namespace App\Filament\Salon\Resources\Reservations\Pages;

use App\Filament\Salon\CurrentSalon;
use App\Filament\Salon\Resources\Reservations\ReservationResource;
use App\Services\ReservationService;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditReservation extends EditRecord
{
    use ConfirmsOverlap;

    protected static string $resource = ReservationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return ReservationResource::formDataFromRecord($this->getRecord());
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $this->haltIfOverlapping($data, $record);

        return app(ReservationService::class)->save(CurrentSalon::get(), ReservationResource::serviceData($data), $record);
    }

    protected function submitAfterOverlapConfirmed(): void
    {
        $this->save();
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
