<?php

namespace App\Http\Resources;

use App\Models\Invitation;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Invitation
 */
class InvitationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'event_id' => $this->event_id,
            'template' => new TemplateResource($this->template),
            'texts' => $this->texts,
            'envelope_color' => $this->envelope_color,
            'seal_color' => $this->seal_color,
            'seal_text' => $this->seal_text,
            'rsvp_deadline' => $this->rsvp_deadline?->toDateString(),
            'first_sent_at' => $this->first_sent_at,
        ];
    }
}
