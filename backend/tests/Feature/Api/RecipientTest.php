<?php

use App\Models\Event;
use App\Models\Invitation;
use App\Models\Recipient;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
    $this->event = Event::factory()->for($this->user, 'owner')->create();
    $this->invitation = Invitation::factory()->create(['event_id' => $this->event->id]);
    $this->url = "/api/v1/events/{$this->event->id}/invitation/recipients";
});

function phones(int $count, int $offset = 0): array
{
    return collect(range(1, $count))
        ->map(fn ($i) => ['phone' => sprintf('0536 %07d', $i + $offset), 'name' => "Misafir {$i}"])
        ->all();
}

it('adds recipients with normalized phones and skips duplicates', function () {
    $registered = User::factory()->create(['phone' => '+905361112233']);

    $this->postJson($this->url, ['recipients' => [
        ['phone' => '0536 111 22 33', 'name' => 'Ali'],
        ['phone' => '+905361112233', 'name' => 'Ali tekrar'],
        ['phone' => '5362223344'],
    ]])
        ->assertCreated()
        ->assertJsonPath('skipped_count', 1)
        ->assertJsonPath('total', 2)
        ->assertJsonPath('limit', 30)
        ->assertJsonCount(2, 'added');

    $this->postJson($this->url, ['recipients' => [['phone' => '0536 222 33 44', 'name' => 'Zaten var']]])
        ->assertCreated()
        ->assertJsonPath('skipped_count', 1)
        ->assertJsonCount(0, 'added');

    $ali = Recipient::where('phone', '+905361112233')->sole();
    expect($ali->name)->toBe('Ali')
        ->and($ali->phone_hash)->toBe(hash('sha256', '+905361112233'))
        ->and($ali->token)->toHaveLength(40)
        ->and($ali->channel)->toBeNull()
        ->and($ali->sent_at)->toBeNull();

    $list = $this->getJson($this->url)->assertOk()->assertJsonCount(2)->json();
    expect(collect($list)->firstWhere('phone', '+905361112233')['is_app_user'])->toBeTrue()
        ->and(collect($list)->firstWhere('phone', '+905362223344')['is_app_user'])->toBeFalse()
        ->and(json_encode($list))->not->toContain($ali->token);
});

it('reports invalid phones per row and adds none', function () {
    $this->postJson($this->url, ['recipients' => [
        ['phone' => '0536 111 22 33'],
        ['phone' => '123'],
    ]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('recipients.1.phone');

    expect(Recipient::count())->toBe(0);
});

it('enforces the free limit atomically: 29 + 2 is rejected and nothing is added', function () {
    $this->postJson($this->url, ['recipients' => phones(29)])->assertCreated()->assertJsonPath('total', 29);

    $this->postJson($this->url, ['recipients' => phones(2, 100)])
        ->assertStatus(402)
        ->assertJsonPath('code', 'pack_required');

    expect(Recipient::count())->toBe(29);

    $this->postJson($this->url, ['recipients' => phones(1, 100)])->assertCreated()->assertJsonPath('total', 30);
});

it('does not count duplicates against the limit', function () {
    $this->postJson($this->url, ['recipients' => phones(30)])->assertCreated();

    $this->postJson($this->url, ['recipients' => phones(3)])
        ->assertCreated()
        ->assertJsonPath('skipped_count', 3);
});

it('lifts the free limit with the pack up to the technical maximum', function () {
    $this->event->update(['pack_purchased_at' => now()]);
    config(['zarf.max_recipient_limit' => 35]);

    $this->postJson($this->url, ['recipients' => phones(35)])->assertCreated()->assertJsonPath('limit', 35);

    $this->postJson($this->url, ['recipients' => phones(1, 100)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('recipients');
});

it('returns 404 when the event has no invitation yet', function () {
    $event = Event::factory()->for($this->user, 'owner')->create();

    $this->getJson("/api/v1/events/{$event->id}/invitation/recipients")->assertNotFound();
    $this->postJson("/api/v1/events/{$event->id}/invitation/recipients", ['recipients' => phones(1)])->assertNotFound();
});

it('deletes unopened recipients only', function () {
    $unopened = Recipient::factory()->for($this->invitation)->create();
    $opened = Recipient::factory()->sentByLink()->for($this->invitation)->create(['opened_at' => now()]);

    $this->deleteJson("{$this->url}/{$unopened->id}")->assertNoContent();
    $this->deleteJson("{$this->url}/{$opened->id}")
        ->assertUnprocessable()
        ->assertJsonPath('errors.recipient.0', 'Zarfını açmış bir davetli silinemez.');

    expect(Recipient::pluck('id')->all())->toBe([$opened->id]);
});

it('cannot touch recipients of other invitations', function () {
    $foreign = Recipient::factory()->create();

    // Kendi etkinliğinin URL'siyle başkasının davetlisi.
    $this->deleteJson("{$this->url}/{$foreign->id}")->assertNotFound();

    // Başkasının etkinliğiyle.
    $this->deleteJson("/api/v1/events/{$foreign->invitation->event_id}/invitation/recipients/{$foreign->id}")
        ->assertNotFound();

    $this->getJson("/api/v1/events/{$foreign->invitation->event_id}/invitation/recipients")->assertNotFound();

    expect(Recipient::whereKey($foreign->id)->exists())->toBeTrue();
});
