<?php

namespace App\Http\Resources;

use App\Models\Recipient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Posta kutusundaki zarf (spec §6.2). `id` recipient id'sidir.
 *
 * @mixin Recipient
 */
class EnvelopeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $invitation = $this->invitation;
        $event = $invitation->event;

        return [
            'id' => $this->id,
            'title' => $event->title,
            'event_type' => $event->type,
            'starts_at' => $event->starts_at,
            'sent_at' => $this->sent_at,
            'opened_at' => $this->opened_at,
            'rsvp_status' => $this->rsvp_status,
            'envelope_color' => $invitation->envelope_color,
            'seal_color' => $invitation->seal_color,
            'seal_text' => $invitation->seal_text,
            'cancelled' => $event->isCancelled(),
        ];
    }
}
