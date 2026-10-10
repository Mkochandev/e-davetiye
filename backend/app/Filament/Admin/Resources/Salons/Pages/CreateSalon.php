<?php

namespace App\Filament\Admin\Resources\Salons\Pages;

use App\Enums\SalonUserRole;
use App\Filament\Admin\Resources\Salons\SalonResource;
use App\Models\Salon;
use App\Services\PhoneNumber;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateSalon extends CreateRecord
{
    protected static string $resource = SalonResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $owner = [
            'name' => $data['owner_name'],
            'email' => mb_strtolower($data['owner_email']),
            'phone' => app(PhoneNumber::class)->tryNormalize($data['owner_phone'] ?? null),
            'role' => SalonUserRole::Owner,
            'is_active' => true,
        ];

        unset($data['owner_name'], $data['owner_email'], $data['owner_phone']);

        $salon = Salon::create($data);
        $salon->users()->create($owner);

        return $salon;
    }

    protected function afterCreate(): void
    {
        $owner = $this->getRecord()->users()->sole();

        // Kayıt kesinleşmeden e-posta gitmesin.
        DB::afterCommit(fn () => $owner->sendPasswordSetupLink());
    }
}
