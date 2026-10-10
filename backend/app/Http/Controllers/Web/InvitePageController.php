<?php

namespace App\Http\Controllers\Web;

use App\Enums\RsvpStatus;
use App\Http\Controllers\Controller;
use App\Models\Recipient;
use App\Services\RsvpService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Uygulaması olmayan misafirin davetiye sayfası `/z/{token}` (spec §3.6, §9).
 */
class InvitePageController extends Controller
{
    public function show(string $token): Response
    {
        $recipient = $this->find($token);
        $event = $recipient->invitation->event;

        if ($event->isCancelled()) {
            return $this->private(response()->view('web.cancelled', ['event' => $event]));
        }

        $view = session('rsvp_saved') ? 'web.thanks' : 'web.invite';

        return $this->private(response()->view($view, [
            'recipient' => $recipient,
            'invitation' => $recipient->invitation,
        ]));
    }

    /**
     * Mühre dokununca çağrılır. Sayfanın açılması sayılmaz; böylece WhatsApp'ın link önizlemesi zarfı "açılmış" yapmaz.
     */
    public function open(string $token, RsvpService $rsvp): Response
    {
        $rsvp->markOpened($this->find($token));

        return response()->noContent();
    }

    public function rsvp(Request $request, string $token, RsvpService $rsvp): RedirectResponse
    {
        $recipient = $this->find($token);
        $data = $request->validate(RsvpService::rules(), [], RsvpService::attributes());

        $rsvp->respond($recipient, RsvpStatus::from($data['status']), isset($data['guest_count']) ? (int) $data['guest_count'] : null);

        return redirect()->route('invite.show', $token)->with('rsvp_saved', true);
    }

    private function find(string $token): Recipient
    {
        return Recipient::sent()
            ->where('token', $token)
            ->with('invitation.event', 'invitation.template')
            ->firstOrFail();
    }

    private function private(Response $response): Response
    {
        return $response->withHeaders([
            'X-Robots-Tag' => 'noindex, nofollow',
            'Referrer-Policy' => 'no-referrer',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
