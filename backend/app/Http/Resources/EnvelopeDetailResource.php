<?php

namespace App\Http\Resources;

use App\Models\Recipient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Davetiyeyi çizmek ve LCV vermek için gereken her şey (spec §6.2, §11).
 *
 * @mixin Recipient
 */
class EnvelopeDetailResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $invitation = $this->invitation;
        $event = $invitation->event;

        return [
            'id' => $this->id,
            'template' => [
                'slug' => $invitation->template->slug,
                'config' => $invitation->template->config,
            ],
            'texts' => $invitation->texts,
            'event' => [
                'type' => $event->type,
                'title' => $event->title,
                'starts_at' => $event->starts_at,
                'ends_at' => $event->ends_at,
                'venue_name' => $event->venue_name,
                'venue_address' => $event->venue_address,
                'latitude' => $event->latitude,
                'longitude' => $event->longitude,
                'cancelled' => $event->isCancelled(),
            ],
            'envelope_color' => $invitation->envelope_color,
            'seal_color' => $invitation->seal_color,
            'seal_text' => $invitation->seal_text,
            'sent_at' => $this->sent_at,
            'opened_at' => $this->opened_at,
            'rsvp_status' => $this->rsvp_status,
            'guest_count' => $this->guest_count,
            'responded_at' => $this->responded_at,
            'rsvp_deadline' => $invitation->rsvp_deadline?->toDateString(),
            'rsvp_open' => ! $event->isCancelled() && ! $invitation->rsvpClosed(),
        ];
    }
}
