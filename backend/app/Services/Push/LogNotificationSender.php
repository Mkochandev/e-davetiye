<?php

namespace App\Services\Push;

use App\Models\User;
use App\Services\PhoneNumber;
use Illuminate\Support\Facades\Log;

/**
 * Bildirimi göndermez, yalnız loglar (telefon maskeli).
 */
class LogNotificationSender implements NotificationSender
{
    public function send(User $user, PushMessage $message): void
    {
        Log::info('Push (gönderilmedi)', [
            'user' => PhoneNumber::mask($user->phone),
            'title' => $message->title,
            'body' => $message->body,
            'data' => $message->data,
        ]);
    }
}
