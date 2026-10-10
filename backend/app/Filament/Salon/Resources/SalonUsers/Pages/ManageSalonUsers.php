<?php

namespace App\Filament\Salon\Resources\SalonUsers\Pages;

use App\Enums\SalonUserRole;
use App\Filament\Salon\CurrentSalon;
use App\Filament\Salon\Resources\SalonUsers\SalonUserResource;
use App\Models\SalonUser;
use Filament\Actions\CreateAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageSalonUsers extends ManageRecords
{
    protected static string $resource = SalonUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Personel ekle')
                ->mutateDataUsing(fn (array $data) => [
                    ...$data,
                    'salon_id' => CurrentSalon::id(),
                    'role' => SalonUserRole::Staff,
                    'is_active' => true,
                ])
                ->after(function (SalonUser $record) {
                    $record->sendPasswordSetupLink();
                    Notification::make()->title('Şifre belirleme e-postası gönderildi')->success()->send();
                }),
        ];
    }
}
