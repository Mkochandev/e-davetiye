<?php

namespace App\Http\Resources;

use App\Models\Event;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Rezervasyon (ücretler dahil) bilgisi asla dönmez.
 *
 * @mixin Event
 */
class EventResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'title' => $this->title,
            'starts_at' => $this->starts_at,
            'ends_at' => $this->ends_at,
            'venue_name' => $this->venue_name,
            'venue_address' => $this->venue_address,
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'created_by' => $this->created_by,
            'salon' => $this->salon_id ? ['name' => $this->salon->name] : null,
            'locked_fields' => $this->lockedFields(),
            'share_headcount_with_salon' => $this->share_headcount_with_salon,
            'has_pack' => $this->hasPack(),
            'recipient_limit' => $this->recipientLimit(),
            'cancelled' => $this->isCancelled(),
            'invitation_status' => match (true) {
                $this->invitation === null => 'none',
                $this->invitation->first_sent_at === null => 'draft',
                default => 'sent',
            },
        ];
    }
}
