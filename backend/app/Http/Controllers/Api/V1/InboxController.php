<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\RsvpStatus;
use App\Http\Controllers\Controller;
use App\Http\Resources\EnvelopeDetailResource;
use App\Http\Resources\EnvelopeResource;
use App\Models\Recipient;
use App\Services\RsvpService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class InboxController extends Controller
{
    /**
     * Kullanıcıya bağlı, gönderilmiş zarflar: açılmamışlar önce, sonra etkinlik tarihi.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $envelopes = $request->user()->receivedRecipients()
            ->sent()
            ->join('invitations', 'invitations.id', '=', 'recipients.invitation_id')
            ->join('events', 'events.id', '=', 'invitations.event_id')
            ->select('recipients.*')
            ->orderByRaw('recipients.opened_at is not null')
            ->orderBy('events.starts_at')
            ->orderBy('recipients.id')
            ->with('invitation.event')
            ->get();

        return EnvelopeResource::collection($envelopes);
    }

    public function show(Recipient $envelope): EnvelopeDetailResource
    {
        return new EnvelopeDetailResource($envelope->load('invitation.template', 'invitation.event'));
    }

    public function open(Recipient $envelope, RsvpService $rsvp): Response
    {
        $rsvp->markOpened($envelope);

        return response()->noContent();
    }

    public function rsvp(Request $request, Recipient $envelope, RsvpService $rsvp): EnvelopeDetailResource
    {
        $data = $request->validate(RsvpService::rules(), [], RsvpService::attributes());

        $rsvp->respond($envelope, RsvpStatus::from($data['status']), $data['guest_count'] ?? null);

        return $this->show($envelope);
    }
}
