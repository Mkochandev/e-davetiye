<?php

namespace App\Events;

use App\Models\Recipient;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Bir davetli LCV verdi ya da değiştirdi (uygulamadan veya web'den).
 */
class RsvpReceived
{
    use Dispatchable;

    public function __construct(public readonly Recipient $recipient) {}
}
