<?php

use App\Enums\RecipientChannel;
use App\Jobs\SendPushNotification;
use App\Models\Event;
use App\Models\Invitation;
use App\Models\Recipient;
use App\Models\User;
use Illuminate\Support\Facades\Queue;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Queue::fake();
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
    $this->event = Event::factory()->for($this->user, 'owner')->create(['title' => 'Elif & Emre']);
    $this->invitation = Invitation::factory()->create(['event_id' => $this->event->id]);
    $this->url = "/api/v1/events/{$this->event->id}/invitation/send";
});

function addRecipient(Invitation $invitation, string $phone, ?string $name = null): Recipient
{
    return Recipient::factory()->create(['invitation_id' => $invitation->id, 'phone' => $phone, 'name' => $name]);
}

it('sends registered users in-app and others by link', function () {
    $guest = User::factory()->create(['phone' => '+905361112233']);
    addRecipient($this->invitation, '+905361112233', 'Ayşe');
    $linkA = addRecipient($this->invitation, '+905362223344', 'Mehmet');
    addRecipient($this->invitation, '+905363334455', 'Zeynep');

    $response = $this->postJson($this->url)
        ->assertOk()
        ->assertJsonPath('in_app_count', 1)
        ->assertJsonCount(2, 'link_recipients')
        ->assertJsonPath('link_recipients.0.id', $linkA->id)
        ->assertJsonPath('link_recipients.0.name', 'Mehmet')
        ->assertJsonPath('link_recipients.0.phone', '+905362223344')
        ->assertJsonPath('link_recipients.0.url', url("/z/{$linkA->token}"));

    $text = rawurlencode('Elif & Emre sizi davet ediyor. Davetiyenizi açmak için: '.url("/z/{$linkA->token}"));
    expect($response->json('link_recipients.0.whatsapp_url'))->toBe("https://wa.me/905362223344?text={$text}");

    $inApp = Recipient::where('phone', '+905361112233')->sole();
    expect($inApp->channel)->toBe(RecipientChannel::InApp)
        ->and($inApp->user_id)->toBe($guest->id)
        ->and($inApp->sent_at)->not->toBeNull()
        ->and($linkA->refresh()->channel)->toBe(RecipientChannel::Link)
        ->and($linkA->user_id)->toBeNull()
        ->and($this->invitation->refresh()->first_sent_at)->not->toBeNull();

    Queue::assertPushed(SendPushNotification::class, 1);
    Queue::assertPushed(SendPushNotification::class, fn ($job) => $job->user->is($guest)
        && $job->message->body === 'Posta kutunda yeni bir zarf var — Elif & Emre'
        && $job->message->data === ['type' => 'envelope', 'recipient_id' => (string) $inApp->id]);
});

it('sends only new recipients when called again', function () {
    addRecipient($this->invitation, '+905362223344');
    $this->postJson($this->url)->assertOk()->assertJsonCount(1, 'link_recipients');
    $firstSentAt = $this->invitation->refresh()->first_sent_at;

    $this->travel(1)->hour();
    $this->postJson($this->url)->assertOk()->assertJsonPath('in_app_count', 0)->assertJsonCount(0, 'link_recipients');

    $new = addRecipient($this->invitation, '+905363334455');
    $this->postJson($this->url)
        ->assertOk()
        ->assertJsonCount(1, 'link_recipients')
        ->assertJsonPath('link_recipients.0.id', $new->id);

    expect($this->invitation->refresh()->first_sent_at->equalTo($firstSentAt))->toBeTrue();
});

it('lists link urls for sent link recipients only', function () {
    $link = addRecipient($this->invitation, '+905362223344');
    $this->postJson($this->url)->assertOk();
    addRecipient($this->invitation, '+905363334455');

    $list = $this->getJson("/api/v1/events/{$this->event->id}/invitation/recipients")->assertOk()->json();

    expect($list[0]['url'])->toBe(url("/z/{$link->token}"))
        ->and($list[0]['whatsapp_url'])->toStartWith('https://wa.me/905362223344?text=')
        ->and($list[1])->not->toHaveKey('url');
});

it('refuses to send a cancelled event', function () {
    $this->event->update(['cancelled_at' => now()]);
    addRecipient($this->invitation, '+905362223344');

    $this->postJson($this->url)->assertUnprocessable()->assertJsonValidationErrors('event');

    expect(Recipient::whereNotNull('sent_at')->count())->toBe(0);
});

it('returns 404 without an invitation', function () {
    $event = Event::factory()->for($this->user, 'owner')->create();

    $this->postJson("/api/v1/events/{$event->id}/invitation/send")->assertNotFound();
});

it('cannot send someone else’s invitation', function () {
    $other = Invitation::factory()->create();
    addRecipient($other, '+905362223344');

    $this->postJson("/api/v1/events/{$other->event_id}/invitation/send")->assertNotFound();

    expect(Recipient::whereNotNull('sent_at')->count())->toBe(0);
});
