<?php

namespace App\Support;

use Carbon\CarbonInterface;

/**
 * Davetiyedeki tarih satırları, İstanbul saatiyle ve Türkçe: "12 Haziran 2027" / "Cumartesi · 19:00".
 */
class EventDate
{
    public const TIMEZONE = 'Europe/Istanbul';

    public static function date(CarbonInterface $startsAt): string
    {
        return self::local($startsAt)->translatedFormat('j F Y');
    }

    public static function dayAndTime(CarbonInterface $startsAt): string
    {
        $local = self::local($startsAt);

        return $local->translatedFormat('l').' · '.$local->format('H:i');
    }

    private static function local(CarbonInterface $at): CarbonInterface
    {
        return $at->copy()->timezone(self::TIMEZONE)->locale('tr');
    }
}
