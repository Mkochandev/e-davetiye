<?php

namespace App\Events;

use App\Models\Recipient;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Kayıtlı bir kullanıcının posta kutusuna zarf düştü (spec §3.4).
 */
class EnvelopeDelivered
{
    use Dispatchable;

    public function __construct(public readonly Recipient $recipient) {}
}
