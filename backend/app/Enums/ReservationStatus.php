<?php

namespace App\Enums;

enum ReservationStatus: string
{
    case Option = 'option';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Option => 'Opsiyon',
            self::Confirmed => 'Kesin',
            self::Cancelled => 'İptal',
        };
    }
}
