<?php

use App\Enums\RecipientChannel;
use App\Enums\RsvpStatus;
use App\Jobs\SendPushNotification;
use App\Models\Event;
use App\Models\Invitation;
use App\Models\Recipient;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Queue::fake();
    $this->guest = User::factory()->create();
    Sanctum::actingAs($this->guest);
});

function envelopeFor(User $user, array $event = [], array $recipient = [], array $invitation = []): Recipient
{
    $invitation = Invitation::factory()->create([
        'event_id' => Event::factory()->create($event)->id,
        ...$invitation,
    ]);

    return Recipient::factory()->create([
        'invitation_id' => $invitation->id,
        'phone' => $user->phone,
        'user_id' => $user->id,
        'channel' => RecipientChannel::InApp,
        'sent_at' => now(),
        ...$recipient,
    ]);
}

it('lists only the user’s sent envelopes, unopened first then by event date', function () {
    $openedSoon = envelopeFor($this->guest, ['starts_at' => now()->addDays(5)], ['opened_at' => now()]);
    $unopenedLate = envelopeFor($this->guest, ['starts_at' => now()->addDays(40)]);
    $unopenedSoon = envelopeFor($this->guest, ['starts_at' => now()->addDays(10), 'title' => 'Elif & Emre'], [], ['seal_text' => 'EE']);
    envelopeFor($this->guest, [], ['sent_at' => null, 'channel' => null]);
    envelopeFor(User::factory()->create());

    $response = $this->getJson('/api/v1/inbox')->assertOk()->assertJsonCount(3);

    expect(array_column($response->json(), 'id'))->toBe([$unopenedSoon->id, $unopenedLate->id, $openedSoon->id]);
    $response->assertJsonPath('0.title', 'Elif & Emre')
        ->assertJsonPath('0.event_type', 'wedding')
        ->assertJsonPath('0.rsvp_status', 'pending')
        ->assertJsonPath('0.envelope_color', 'sage')
        ->assertJsonPath('0.seal_color', 'bordo')
        ->assertJsonPath('0.seal_text', 'EE')
        ->assertJsonPath('0.cancelled', false);
    expect($response->json('0'))->toHaveKeys(['starts_at', 'sent_at', 'opened_at']);
});

it('shows everything needed to draw the invitation', function () {
    $deadline = now()->addDays(3)->toDateString();
    $envelope = envelopeFor($this->guest, ['venue_name' => 'Gül Salonu'], [], ['rsvp_deadline' => $deadline]);

    $this->getJson("/api/v1/inbox/{$envelope->id}")
        ->assertOk()
        ->assertJsonPath('id', $envelope->id)
        ->assertJsonPath('template.config.frame', 'double')
        ->assertJsonPath('texts.names', 'Elif & Emre')
        ->assertJsonPath('event.venue_name', 'Gül Salonu')
        ->assertJsonPath('event.latitude', 37.0662)
        ->assertJsonPath('rsvp_deadline', $deadline)
        ->assertJsonPath('rsvp_open', true)
        ->assertJsonMissingPath('token')
        ->assertJsonMissingPath('event.couple_phone');
});

it('hides other people’s and unsent envelopes', function () {
    $others = envelopeFor(User::factory()->create());
    $unsent = envelopeFor($this->guest, [], ['sent_at' => null, 'channel' => null]);

    foreach ([$others, $unsent] as $envelope) {
        $this->getJson("/api/v1/inbox/{$envelope->id}")->assertNotFound();
        $this->postJson("/api/v1/inbox/{$envelope->id}/open")->assertNotFound();
        $this->postJson("/api/v1/inbox/{$envelope->id}/rsvp", ['status' => 'not_attending'])->assertNotFound();
    }

    expect($others->refresh()->opened_at)->toBeNull()
        ->and($others->rsvp_status)->toBe(RsvpStatus::Pending);
});

it('marks an envelope opened once', function () {
    $envelope = envelopeFor($this->guest);

    $this->postJson("/api/v1/inbox/{$envelope->id}/open")->assertNoContent();
    $openedAt = $envelope->refresh()->opened_at;
    expect($openedAt)->not->toBeNull();

    $this->travel(2)->hours();
    $this->postJson("/api/v1/inbox/{$envelope->id}/open")->assertNoContent();

    expect($envelope->refresh()->opened_at->equalTo($openedAt))->toBeTrue();
});

it('records an rsvp and notifies the owner', function () {
    $envelope = envelopeFor($this->guest, ['title' => 'Elif & Emre'], ['name' => 'Ayşe Yılmaz']);
    $owner = $envelope->invitation->event->owner;

    $this->postJson("/api/v1/inbox/{$envelope->id}/rsvp", ['status' => 'attending', 'guest_count' => 3])
        ->assertOk()
        ->assertJsonPath('rsvp_status', 'attending')
        ->assertJsonPath('guest_count', 3);

    expect($envelope->refresh()->responded_at)->not->toBeNull()
        ->and($envelope->opened_at)->not->toBeNull();

    Queue::assertPushed(SendPushNotification::class, fn ($job) => $job->user->is($owner)
        && $job->message->body === 'Ayşe Yılmaz katılıyor (3 kişi) — Elif & Emre'
        && $job->message->data === ['type' => 'rsvp', 'event_id' => (string) $envelope->invitation->event_id]);

    // Son tarihe kadar değiştirilebilir; katılmıyorsa kişi sayısı boşalır.
    $this->postJson("/api/v1/inbox/{$envelope->id}/rsvp", ['status' => 'not_attending', 'guest_count' => 4])
        ->assertOk()
        ->assertJsonPath('rsvp_status', 'not_attending')
        ->assertJsonPath('guest_count', null);
});

it('validates the rsvp', function (array $body, string $field) {
    $envelope = envelopeFor($this->guest);

    $this->postJson("/api/v1/inbox/{$envelope->id}/rsvp", $body)->assertUnprocessable()->assertJsonValidationErrors($field);
})->with([
    'no status' => [[], 'status'],
    'pending' => [['status' => 'pending'], 'status'],
    'no guests' => [['status' => 'attending'], 'guest_count'],
    'zero guests' => [['status' => 'attending', 'guest_count' => 0], 'guest_count'],
    'too many guests' => [['status' => 'attending', 'guest_count' => 11], 'guest_count'],
]);

it('accepts rsvp on the deadline day and rejects it after', function () {
    $this->travelTo(now('Europe/Istanbul')->setTime(23, 30));
    $envelope = envelopeFor($this->guest, [], [], ['rsvp_deadline' => now('Europe/Istanbul')->toDateString()]);

    $this->postJson("/api/v1/inbox/{$envelope->id}/rsvp", ['status' => 'attending', 'guest_count' => 2])->assertOk();

    $this->travel(1)->hour();

    $this->postJson("/api/v1/inbox/{$envelope->id}/rsvp", ['status' => 'not_attending'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');
    $this->getJson("/api/v1/inbox/{$envelope->id}")->assertJsonPath('rsvp_open', false);

    expect($envelope->refresh()->rsvp_status)->toBe(RsvpStatus::Attending);
});

it('closes rsvp once the event has started, even without a deadline', function () {
    $envelope = envelopeFor($this->guest, ['starts_at' => now()->addHour()]);

    $this->postJson("/api/v1/inbox/{$envelope->id}/rsvp", ['status' => 'attending', 'guest_count' => 1])->assertOk();

    $this->travel(2)->hours();

    $this->postJson("/api/v1/inbox/{$envelope->id}/rsvp", ['status' => 'not_attending'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('status');
    $this->getJson("/api/v1/inbox/{$envelope->id}")->assertJsonPath('rsvp_open', false);
});

it('shows a cancelled event and refuses rsvp', function () {
    $envelope = envelopeFor($this->guest, ['cancelled_at' => now()]);

    $this->getJson('/api/v1/inbox')->assertJsonPath('0.cancelled', true);
    $this->postJson("/api/v1/inbox/{$envelope->id}/rsvp", ['status' => 'not_attending'])->assertUnprocessable();
});

it('counts stats for the owner only', function () {
    $owner = User::factory()->create();
    $event = Event::factory()->for($owner, 'owner')->create();
    $invitation = Invitation::factory()->create(['event_id' => $event->id]);
    $make = fn (array $state) => Recipient::factory()->create(['invitation_id' => $invitation->id, ...$state]);

    $make(['sent_at' => now(), 'opened_at' => now(), 'rsvp_status' => 'attending', 'guest_count' => 2, 'responded_at' => now()]);
    $make(['sent_at' => now(), 'opened_at' => now(), 'rsvp_status' => 'attending', 'guest_count' => 3, 'responded_at' => now()]);
    $make(['sent_at' => now(), 'opened_at' => now(), 'rsvp_status' => 'not_attending', 'responded_at' => now()]);
    $make(['sent_at' => now(), 'opened_at' => now()]);
    $make(['sent_at' => now()]);
    $make([]);

    $this->getJson("/api/v1/events/{$event->id}/invitation/stats")->assertNotFound();

    Sanctum::actingAs($owner);
    $this->getJson("/api/v1/events/{$event->id}/invitation/stats")
        ->assertOk()
        ->assertExactJson([
            'sent' => 5,
            'opened' => 4,
            'attending' => 2,
            'attending_guests' => 5,
            'not_attending' => 1,
            'pending' => 1,
            'unopened' => 1,
        ]);
});
