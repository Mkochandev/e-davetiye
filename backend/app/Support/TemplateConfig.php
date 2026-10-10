<?php

namespace App\Support;

/**
 * Şablon config şeması v1 (spec §11).
 */
class TemplateConfig
{
    public const FRAMES = ['none', 'single', 'double'];

    public const ORNAMENTS = ['none', 'dot-rule', 'top-band'];

    public const FONTS = ['Fraunces', 'Plus Jakarta Sans'];

    public const COLOR_KEYS = ['paper', 'ink', 'muted', 'accent'];

    /**
     * @return list<string> Türkçe hata mesajları; boşsa geçerli.
     */
    public static function errors(mixed $config): array
    {
        if (! is_array($config)) {
            return ['Config geçerli bir JSON nesnesi olmalı.'];
        }

        $errors = [];

        if (($config['v'] ?? null) !== 1) {
            $errors[] = '"v" değeri 1 olmalı.';
        }

        foreach (self::COLOR_KEYS as $key) {
            if (! self::isHex($config[$key] ?? null)) {
                $errors[] = "\"{$key}\" #RRGGBB biçiminde renk olmalı.";
            }
        }

        if (! in_array($config['frame'] ?? null, self::FRAMES, true)) {
            $errors[] = '"frame" şunlardan biri olmalı: '.implode(', ', self::FRAMES).'.';
        } elseif ($config['frame'] !== 'none' && ! self::isHex($config['frame_color'] ?? null)) {
            $errors[] = '"frame_color" #RRGGBB biçiminde renk olmalı.';
        }

        if (! in_array($config['ornament'] ?? null, self::ORNAMENTS, true)) {
            $errors[] = '"ornament" şunlardan biri olmalı: '.implode(', ', self::ORNAMENTS).'.';
        }

        foreach (['display_font', 'body_font'] as $key) {
            if (! in_array($config[$key] ?? null, self::FONTS, true)) {
                $errors[] = "\"{$key}\" şunlardan biri olmalı: ".implode(', ', self::FONTS).'.';
            }
        }

        return $errors;
    }

    private static function isHex(mixed $value): bool
    {
        return is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1;
    }
}
