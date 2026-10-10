<?php

use App\Enums\PurchaseStatus;
use App\Models\Event;
use App\Models\Invitation;
use App\Models\Purchase;
use App\Models\Recipient;
use App\Models\Template;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
    $this->event = Event::factory()->for($this->user, 'owner')->create();
});

function buyPack(int $eventId, string $data = 'fake-ok:tx1', array $extra = [])
{
    return test()->postJson('/api/v1/purchases', [
        'platform' => 'android',
        'product_id' => 'event_pack',
        'event_id' => $eventId,
        'verification_data' => $data,
        ...$extra,
    ]);
}

it('opens the pack with the fake verifier', function () {
    buyPack($this->event->id)
        ->assertOk()
        ->assertJsonPath('transaction_id', 'fake-tx1')
        ->assertJsonPath('event.has_pack', true)
        ->assertJsonPath('event.recipient_limit', 1000);

    $purchase = Purchase::sole();
    expect($purchase->status)->toBe(PurchaseStatus::Verified)
        ->and($purchase->user_id)->toBe($this->user->id)
        ->and($purchase->event_id)->toBe($this->event->id)
        ->and($purchase->verified_at)->not->toBeNull()
        ->and($this->event->refresh()->hasPack())->toBeTrue();
});

it('is idempotent for the same transaction', function () {
    buyPack($this->event->id)->assertOk();
    $openedAt = $this->event->refresh()->pack_purchased_at;

    $this->travel(1)->hour();
    buyPack($this->event->id)->assertOk()->assertJsonPath('transaction_id', 'fake-tx1');

    expect(Purchase::count())->toBe(1)
        ->and($this->event->refresh()->pack_purchased_at->equalTo($openedAt))->toBeTrue();
});

it('rejects a new transaction for an event that already has the pack', function () {
    buyPack($this->event->id)->assertOk();

    buyPack($this->event->id, 'fake-ok:tx2')
        ->assertStatus(409)
        ->assertJsonPath('code', 'pack_exists');

    expect(Purchase::count())->toBe(1);
});

it('does not open the pack when verification fails', function () {
    buyPack($this->event->id, 'not-a-real-receipt')
        ->assertUnprocessable()
        ->assertJsonPath('code', 'verification_failed');

    buyPack($this->event->id, 'fake-fail:tx9')->assertUnprocessable();

    expect($this->event->refresh()->hasPack())->toBeFalse()
        ->and(Purchase::sole()->status)->toBe(PurchaseStatus::Failed)
        ->and(Purchase::sole()->transaction_id)->toBe('fake-tx9');
});

it('cannot buy for someone else’s event', function () {
    $other = Event::factory()->create();

    buyPack($other->id)->assertNotFound();

    expect($other->refresh()->hasPack())->toBeFalse()
        ->and(Purchase::count())->toBe(0);
});

it('validates the request', function () {
    buyPack($this->event->id, extra: ['product_id' => 'other'])->assertUnprocessable()->assertJsonValidationErrors('product_id');
    buyPack($this->event->id, extra: ['platform' => 'web'])->assertUnprocessable()->assertJsonValidationErrors('platform');
    $this->postJson('/api/v1/purchases', [])->assertUnprocessable()->assertJsonValidationErrors(['platform', 'product_id', 'event_id', 'verification_data']);
});

it('lifts the free limits once the pack is bought (spec §16 scenario 5)', function () {
    $invitation = Invitation::factory()->create(['event_id' => $this->event->id]);
    Recipient::factory()->count(30)->create(['invitation_id' => $invitation->id]);
    $premium = Template::factory()->premium()->create();
    $recipientsUrl = "/api/v1/events/{$this->event->id}/invitation/recipients";

    $this->postJson($recipientsUrl, ['recipients' => [['phone' => '0536 999 88 77']]])->assertStatus(402);

    buyPack($this->event->id)->assertOk();
    buyPack($this->event->id)->assertOk();

    $this->postJson($recipientsUrl, ['recipients' => [['phone' => '0536 999 88 77']]])->assertCreated()->assertJsonPath('total', 31);
    $this->putJson("/api/v1/events/{$this->event->id}/invitation", [
        'template_id' => $premium->id,
        'envelope_color' => 'blush',
        'seal_color' => 'gold',
    ])->assertOk();

    expect(Purchase::count())->toBe(1);
});
