<?php

use App\Enums\EventCreator;
use App\Models\Event;
use App\Models\Invitation;
use App\Models\Reservation;
use App\Models\Salon;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->user = User::factory()->create(['phone' => '+905321112233']);
    Sanctum::actingAs($this->user);
});

function salonEventFor(User $user): Event
{
    $salon = Salon::factory()->create(['name' => 'Gül Bahçesi']);
    $event = Event::factory()->fromSalon($user->phone)->create([
        'salon_id' => $salon->id,
        'owner_user_id' => $user->id,
        'starts_at' => '2027-06-12 16:00:00',
        'ends_at' => '2027-06-12 20:00:00',
        'venue_name' => 'Gül Bahçesi',
    ]);
    Reservation::factory()->create([
        'event_id' => $event->id,
        'salon_id' => $salon->id,
        'contact_phone' => $user->phone,
        'total_amount' => 15_000_000,
        'deposit_amount' => 3_000_000,
        'paid_amount' => 1_234_500,
    ]);

    return $event;
}

it('creates an event for the user, storing times in UTC', function () {
    $this->postJson('/api/v1/events', [
        'type' => 'engagement',
        'title' => 'Ayşe & Can',
        'starts_at' => '2027-05-01T19:00:00+03:00',
        'ends_at' => '2027-05-01 23:00',
        'venue_name' => 'Bahçe',
        'venue_address' => 'Gaziantep',
        'latitude' => 37.06,
        'longitude' => 37.38,
    ])
        ->assertCreated()
        ->assertJsonPath('created_by', 'user')
        ->assertJsonPath('locked_fields', [])
        ->assertJsonPath('salon', null)
        ->assertJsonPath('invitation_status', 'none')
        ->assertJsonPath('recipient_limit', 30);

    $event = Event::sole();
    expect($event->owner_user_id)->toBe($this->user->id)
        ->and($event->created_by)->toBe(EventCreator::User)
        ->and($event->starts_at->toIso8601String())->toBe('2027-05-01T16:00:00+00:00')
        // Saat dilimi verilmezse İstanbul saati.
        ->and($event->ends_at->toIso8601String())->toBe('2027-05-01T20:00:00+00:00');
});

it('validates event fields', function () {
    $this->postJson('/api/v1/events', ['type' => 'party', 'starts_at' => 'yarın', 'ends_at' => '2020-01-01'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type', 'title', 'starts_at', 'venue_name']);

    $this->postJson('/api/v1/events', [
        'type' => 'wedding', 'title' => 'X Y', 'venue_name' => 'Salon',
        'starts_at' => '2027-05-01 19:00', 'ends_at' => '2027-05-01 18:00',
    ])->assertJsonValidationErrors(['ends_at']);
});

it('lists only own events with salon name and locked fields', function () {
    $salonEvent = salonEventFor($this->user);
    $own = Event::factory()->for($this->user, 'owner')->create();
    Event::factory()->create();
    Event::factory()->fromSalon()->create();

    $response = $this->getJson('/api/v1/events')
        ->assertOk()
        ->assertJsonCount(2);

    expect(collect($response->json())->pluck('id')->sort()->values()->all())
        ->toBe(collect([$salonEvent->id, $own->id])->sort()->values()->all());

    $salonJson = collect($response->json())->firstWhere('id', $salonEvent->id);
    expect($salonJson['salon'])->toBe(['name' => 'Gül Bahçesi'])
        ->and($salonJson['locked_fields'])->toBe(Event::SALON_LOCKED_FIELDS);
});

it('never exposes reservation fees', function () {
    $event = salonEventFor($this->user);

    foreach (['/api/v1/events', "/api/v1/events/{$event->id}"] as $url) {
        $body = $this->getJson($url)->assertOk()->getContent();

        expect($body)->not->toContain('amount')
            ->not->toContain('15000000')
            ->not->toContain('1234500')
            ->not->toContain('reservation');
    }
});

it('returns 404 for other users events', function (string $method, string $suffix) {
    $other = Event::factory()->create();

    $this->json($method, "/api/v1/events/{$other->id}{$suffix}", ['share' => false, 'title' => 'Ele geçir', 'template_id' => 1])
        ->assertNotFound()
        ->assertJsonPath('message', 'Kayıt bulunamadı.');

    expect($other->refresh()->title)->not->toBe('Ele geçir');
})->with([
    ['GET', ''],
    ['PATCH', ''],
    ['PATCH', '/headcount-sharing'],
    ['GET', '/invitation'],
    ['PUT', '/invitation'],
    ['GET', '/invitation/recipients'],
    ['POST', '/invitation/recipients'],
]);

it('updates own event fields', function () {
    $event = Event::factory()->for($this->user, 'owner')->create();

    $this->patchJson("/api/v1/events/{$event->id}", [
        'title' => 'Yeni Başlık',
        'starts_at' => '2027-07-01T20:00:00+03:00',
        'venue_name' => 'Yeni Mekan',
    ])
        ->assertOk()
        ->assertJsonPath('title', 'Yeni Başlık')
        ->assertJsonPath('venue_name', 'Yeni Mekan');

    expect($event->refresh()->starts_at->toIso8601String())->toBe('2027-07-01T17:00:00+00:00');
});

it('rejects changing locked fields on salon events', function (array $payload, string $field) {
    $event = salonEventFor($this->user);

    $this->patchJson("/api/v1/events/{$event->id}", $payload)
        ->assertUnprocessable()
        ->assertJsonPath("errors.{$field}.0", 'Salonun eklediği etkinlikte bu alan değiştirilemez.');

    expect($event->refresh()->venue_name)->toBe('Gül Bahçesi')
        ->and($event->starts_at->toIso8601String())->toBe('2027-06-12T16:00:00+00:00');
})->with([
    'start' => [['starts_at' => '2027-06-13T19:00:00+03:00'], 'starts_at'],
    'end' => [['ends_at' => '2027-06-13T01:00:00+03:00'], 'ends_at'],
    'venue' => [['venue_name' => 'Başka Yer'], 'venue_name'],
    'address' => [['venue_address' => 'Başka adres'], 'venue_address'],
    'location' => [['latitude' => 40, 'longitude' => 30], 'latitude'],
]);

it('allows editing unlocked fields and resending unchanged locked values on salon events', function () {
    $event = salonEventFor($this->user);

    $this->patchJson("/api/v1/events/{$event->id}", [
        'title' => 'Elif & Emre',
        'type' => 'henna',
        'starts_at' => '2027-06-12T19:00:00+03:00',
        'venue_name' => 'Gül Bahçesi',
    ])
        ->assertOk()
        ->assertJsonPath('title', 'Elif & Emre')
        ->assertJsonPath('type', 'henna');
});

it('toggles headcount sharing with the salon', function () {
    $event = salonEventFor($this->user);

    $this->patchJson("/api/v1/events/{$event->id}/headcount-sharing", ['share' => false])
        ->assertOk()
        ->assertJsonPath('share_headcount_with_salon', false);

    expect($event->refresh()->share_headcount_with_salon)->toBeFalse();

    $this->patchJson("/api/v1/events/{$event->id}/headcount-sharing", [])->assertJsonValidationErrors('share');
});

it('reports invitation status', function () {
    $event = Event::factory()->for($this->user, 'owner')->create();
    $invitation = Invitation::factory()->create(['event_id' => $event->id]);

    $this->getJson("/api/v1/events/{$event->id}")->assertJsonPath('invitation_status', 'draft');

    $invitation->update(['first_sent_at' => now()]);

    $this->getJson("/api/v1/events/{$event->id}")->assertJsonPath('invitation_status', 'sent');
});

it('requires authentication', function () {
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/v1/events')->assertUnauthorized();
    $this->getJson('/api/v1/events/1')->assertUnauthorized();
});
