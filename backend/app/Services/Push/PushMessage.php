<?php

namespace App\Services\Push;

/**
 * Tek bir push bildirimi. Metinlerde emoji yok (spec §13).
 */
final class PushMessage
{
    /**
     * @param  array<string, string>  $data  Mobilin bildirime dokununca açacağı ekran için (ör. `recipient_id`).
     */
    public function __construct(
        public readonly string $title,
        public readonly string $body,
        public readonly array $data = [],
    ) {}
}
