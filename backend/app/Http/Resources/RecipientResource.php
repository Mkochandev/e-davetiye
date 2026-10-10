<?php

namespace App\Http\Resources;

use App\Enums\RecipientChannel;
use App\Models\Recipient;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Davetiye sahibinin gördüğü davetli satırı.
 *
 * @mixin Recipient
 */
class RecipientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $isLink = $this->channel === RecipientChannel::Link;

        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'is_app_user' => $this->user_id !== null || $this->appUser !== null,
            'channel' => $this->channel,
            'sent_at' => $this->sent_at,
            'opened_at' => $this->opened_at,
            'rsvp_status' => $this->rsvp_status,
            'guest_count' => $this->guest_count,
            'responded_at' => $this->responded_at,
            // Link alıcısına WhatsApp hatırlatması için (spec §10 M16).
            'url' => $this->when($isLink, fn () => $this->webUrl()),
            'whatsapp_url' => $this->when($isLink, fn () => $this->whatsappUrl()),
        ];
    }
}
