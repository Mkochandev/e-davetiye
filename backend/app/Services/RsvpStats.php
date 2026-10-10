<?php

namespace App\Services;

use App\Enums\RsvpStatus;
use App\Models\Event;
use Illuminate\Support\Facades\DB;

/**
 * Gönderilmiş zarfların LCV sayıları. Kovalar ayrık: açılmadı → açıldı ama yanıt yok (bekleyen) → yanıt verdi.
 */
class RsvpStats
{
    /**
     * @return array{sent: int, opened: int, attending: int, attending_guests: int, not_attending: int, pending: int, unopened: int}
     */
    public static function forEvent(Event $event): array
    {
        $row = DB::table('recipients')
            ->join('invitations', 'invitations.id', '=', 'recipients.invitation_id')
            ->where('invitations.event_id', $event->id)
            ->whereNotNull('recipients.sent_at')
            ->selectRaw('count(*) as sent')
            ->selectRaw('count(recipients.opened_at) as opened')
            ->selectRaw('count(*) filter (where rsvp_status = ?) as attending', [RsvpStatus::Attending->value])
            ->selectRaw('coalesce(sum(guest_count) filter (where rsvp_status = ?), 0) as attending_guests', [RsvpStatus::Attending->value])
            ->selectRaw('count(*) filter (where rsvp_status = ?) as not_attending', [RsvpStatus::NotAttending->value])
            ->selectRaw('count(*) filter (where rsvp_status = ? and opened_at is not null) as pending', [RsvpStatus::Pending->value])
            ->selectRaw('count(*) filter (where opened_at is null) as unopened')
            ->first();

        return array_map('intval', (array) $row);
    }
}
