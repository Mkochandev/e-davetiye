<?php

namespace App\Listeners;

use App\Enums\RsvpStatus;
use App\Events\EnvelopeDelivered;
use App\Events\RsvpReceived;
use App\Events\SalonEventLinked;
use App\Jobs\SendPushNotification;
use App\Services\PhoneNumber;
use App\Services\Push\PushMessage;

/**
 * Bildirim olaylarını push işine çevirir (spec §13). Metinlerde emoji yok.
 */
class QueuePushNotifications
{
    public function __construct(private readonly PhoneNumber $phones) {}

    public function handleEnvelopeDelivered(EnvelopeDelivered $event): void
    {
        $recipient = $event->recipient;

        if ($recipient->user === null) {
            return;
        }

        SendPushNotification::dispatch($recipient->user, new PushMessage(
            'Yeni zarf',
            'Posta kutunda yeni bir zarf var — '.$recipient->invitation->event->title,
            ['type' => 'envelope', 'recipient_id' => (string) $recipient->id],
        ));
    }

    public function handleRsvpReceived(RsvpReceived $event): void
    {
        $recipient = $event->recipient;
        $owner = $recipient->invitation->event->owner;

        if ($owner === null) {
            return;
        }

        $who = $recipient->name ?: $this->phones->formatNational($recipient->phone);
        $answer = $recipient->rsvp_status === RsvpStatus::Attending
            ? "katılıyor ({$recipient->guest_count} kişi)"
            : 'katılamıyor';

        SendPushNotification::dispatch($owner, new PushMessage(
            'Yeni LCV',
            "{$who} {$answer} — ".$recipient->invitation->event->title,
            ['type' => 'rsvp', 'event_id' => (string) $recipient->invitation->event_id],
        ));
    }

    public function handleSalonEventLinked(SalonEventLinked $event): void
    {
        $owner = $event->event->owner;

        if ($owner === null) {
            return;
        }

        SendPushNotification::dispatch($owner, new PushMessage(
            'Salonun etkinliğini ekledi',
            $event->event->salon->name.' etkinliğini ekledi — '.$event->event->title,
            ['type' => 'event', 'event_id' => (string) $event->event->id],
        ));
    }
}
