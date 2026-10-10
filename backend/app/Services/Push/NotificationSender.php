<?php

namespace App\Services\Push;

use App\Models\User;

/**
 * Kullanıcının cihazlarına push gönderir. Gerçek FCM gönderici Adım 9'da gelecek.
 */
interface NotificationSender
{
    public function send(User $user, PushMessage $message): void;
}
