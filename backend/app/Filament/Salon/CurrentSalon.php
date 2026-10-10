<?php

namespace App\Filament\Salon;

use App\Models\Salon;
use App\Models\SalonUser;
use Filament\Facades\Filament;

/**
 * Salon panelinde oturumdaki kullanıcı ve salonu. Tüm sorgular bu salon_id ile kapsanır.
 */
class CurrentSalon
{
    public static function user(): SalonUser
    {
        /** @var SalonUser $user */
        $user = Filament::auth()->user();

        return $user;
    }

    public static function id(): int
    {
        return self::user()->salon_id;
    }

    public static function get(): Salon
    {
        return self::user()->salon;
    }

    public static function isOwner(): bool
    {
        return Filament::auth()->check() && self::user()->isOwner();
    }
}
