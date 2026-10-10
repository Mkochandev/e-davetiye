<?php

namespace App\Filament\Admin\Resources\Salons\Pages;

use App\Filament\Admin\Resources\Salons\SalonResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSalons extends ListRecords
{
    protected static string $resource = SalonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
