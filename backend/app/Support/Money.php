<?php

namespace App\Support;

/**
 * Tutarlar kuruş (integer) saklanır; panelde TL olarak girilir ve gösterilir.
 */
class Money
{
    public static function toKurus(int|float|string|null $lira): int
    {
        if ($lira === null || $lira === '') {
            return 0;
        }

        if (is_string($lira)) {
            $lira = str_replace(',', '.', $lira);
        }

        return (int) round(((float) $lira) * 100);
    }

    public static function toLira(?int $kurus): float
    {
        return ($kurus ?? 0) / 100;
    }

    /**
     * 150000000 → "1.500.000,00 ₺"
     */
    public static function format(?int $kurus): string
    {
        return number_format(self::toLira($kurus), 2, ',', '.').' ₺';
    }
}
