<?php

namespace App\View\Components;

use App\Models\Invitation;
use App\Support\EventDate;
use Illuminate\View\Component;
use Illuminate\View\View;

/**
 * Davetiye kartı: şablon config'i (spec §11) + davetiye metinleri + etkinlik bilgisi.
 * Çizim sırası sabit; mobil `InvitationCard` widget'ı aynı sırayı ve ölçüleri izler.
 */
class InvitationCard extends Component
{
    /** @var array<string, mixed> */
    public array $config;

    /** @var array<string, string> */
    public array $texts;

    public string $date;

    public string $dayAndTime;

    public ?string $venueName;

    public ?string $venueAddress;

    public function __construct(public Invitation $invitation)
    {
        $event = $invitation->event;

        $this->config = $invitation->template->config;
        $this->texts = array_map(fn ($v) => trim((string) $v), $invitation->texts + ['label' => '', 'intro' => '', 'names' => '', 'message' => '']);
        $this->date = EventDate::date($event->starts_at);
        $this->dayAndTime = EventDate::dayAndTime($event->starts_at);
        $this->venueName = $event->venue_name;
        $this->venueAddress = $event->venue_address;
    }

    public function font(string $key): string
    {
        return $this->config[$key] === 'Fraunces'
            ? "'Fraunces', Georgia, serif"
            : "'Plus Jakarta Sans', system-ui, sans-serif";
    }

    public function render(): View
    {
        return view('components.invitation-card');
    }
}
