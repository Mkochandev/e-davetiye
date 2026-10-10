<?php

namespace App\Services\Sms;

use App\Services\PhoneNumber;
use Illuminate\Support\Facades\Log;

/**
 * Yalnız local/testing: SMS göndermez, mesajı (kod dahil) loga yazar. Telefon maskelenir.
 */
class LogSmsSender implements SmsSender
{
    public function send(string $phone, string $message): void
    {
        Log::info('SMS (gönderilmedi)', [
            'phone' => PhoneNumber::mask($phone),
            'message' => $message,
        ]);
    }
}
