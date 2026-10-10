<?php

namespace App\Jobs;

use App\Models\User;
use App\Services\Push\NotificationSender;
use App\Services\Push\PushMessage;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Push'u kuyrukta gönderir (spec §13). Transaction commit edilmeden kuyruğa düşmez.
 */
class SendPushNotification implements ShouldQueueAfterCommit
{
    use Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly User $user,
        public readonly PushMessage $message,
    ) {}

    public function handle(NotificationSender $sender): void
    {
        $sender->send($this->user, $this->message);
    }
}
