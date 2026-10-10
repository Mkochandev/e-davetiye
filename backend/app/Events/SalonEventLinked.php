<?php

namespace App\Events;

use App\Models\Event;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * Salonun açtığı etkinlik kayıtlı bir çifte bağlandı (spec §3.1 adım 3).
 */
class SalonEventLinked
{
    use Dispatchable;

    public function __construct(public readonly Event $event) {}
}
