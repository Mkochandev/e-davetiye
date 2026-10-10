<?php

return [

    /*
    | Ücretsiz / Etkinlik Paketi sınırları (spec §4). Sunucuda zorlanır.
    */

    'free_recipient_limit' => 30,

    'max_recipient_limit' => 1000,

    'free_envelope_colors' => ['sage', 'navy'],

    'free_seal_colors' => ['bordo'],

    /*
    | Renk anahtarları ve değerleri (spec §15).
    */

    'envelope_colors' => [
        'sage' => '#B7C4B1',
        'navy' => '#2E3B5E',
        'blush' => '#E8CBC6',
        'sand' => '#DCCFB8',
    ],

    'seal_colors' => [
        'bordo' => '#8A1F3D',
        'gold' => '#A8833A',
        'ink' => '#1D2340',
        'forest' => '#2F5A45',
    ],

    'product_id' => 'event_pack',

    /*
    | Mağaza bağlantıları (/indir ve web davetiye teşekkür ekranı). Boşsa yer tutucu gösterilir.
    */

    'store_urls' => [
        'ios' => env('APP_STORE_URL'),
        'android' => env('PLAY_STORE_URL'),
    ],

    /*
    | Telefonla giriş (spec §2).
    */

    'otp' => [
        'length' => 6,
        'ttl_minutes' => 5,
        'max_attempts' => 5,
        'per_phone_cooldown_seconds' => 60,
        'per_phone_per_hour' => 5,
        'per_ip_per_hour' => 20,
    ],

    // Açık rıza metninin sürümü; kullanıcı onayladığında users.kvkk_version'a yazılır.
    'kvkk_version' => env('KVKK_VERSION', '2026-10'),

];
