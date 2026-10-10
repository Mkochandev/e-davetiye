<?php

namespace App\Support;

/**
 * Zarf ve mühür renkleri (spec §15). Mobil `EnvelopeView` aynı hesabı yapar.
 */
class Palette
{
    public const FLAP_FACTOR = 0.88;

    public const INNER_FACTOR = 0.62;

    public static function envelope(string $key): string
    {
        return config("zarf.envelope_colors.{$key}") ?? config('zarf.envelope_colors.'.InvitationDefaults::ENVELOPE_COLOR);
    }

    public static function seal(string $key): string
    {
        return config("zarf.seal_colors.{$key}") ?? config('zarf.seal_colors.'.InvitationDefaults::SEAL_COLOR);
    }

    /**
     * Her kanalı çarpanla koyulaştırır: `#B7C4B1` × 0,88.
     */
    public static function scale(string $hex, float $factor): string
    {
        $channels = array_map(
            fn (string $c) => (int) round(hexdec($c) * $factor),
            str_split(ltrim($hex, '#'), 2),
        );

        return vsprintf('#%02X%02X%02X', array_map(fn (int $c) => max(0, min(255, $c)), $channels));
    }
}
