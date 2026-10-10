<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Resend, Postmark, AWS, and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'resend' => [
        'key' => env('RESEND_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'netgsm' => [
        'usercode' => env('NETGSM_USERCODE'),
        'password' => env('NETGSM_PASSWORD'),
        'header' => env('NETGSM_HEADER'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    | Etkinlik Paketi doğrulaması (spec §12). Anahtar dosyaları repoya girmez; yol .env'den.
    */

    'apple_iap' => [
        'key_id' => env('APPLE_IAP_KEY_ID'),
        'issuer_id' => env('APPLE_IAP_ISSUER_ID'),
        'bundle_id' => env('APPLE_BUNDLE_ID'),
        'private_key_path' => env('APPLE_IAP_PRIVATE_KEY_PATH'),
    ],

    'google_play' => [
        'package_name' => env('GOOGLE_PLAY_PACKAGE_NAME'),
        'service_account_path' => env('GOOGLE_PLAY_SERVICE_ACCOUNT_PATH'),
    ],

];
