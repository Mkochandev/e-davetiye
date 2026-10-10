<?php

use App\Enums\EventType;
use App\Models\Event;
use App\Models\Invitation;
use App\Models\Template;
use App\Models\User;
use Database\Seeders\TemplateSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(TemplateSeeder::class);
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
    $this->event = Event::factory()->for($this->user, 'owner')->create([
        'type' => EventType::Henna,
        'title' => 'Elif & Emre',
        'starts_at' => '2027-06-12 16:00:00',
    ]);
    $this->free = Template::where('slug', 'klasik')->sole();
    $this->premium = Template::where('slug', 'altin-varak')->sole();
});

it('lists active templates with premium flags, filtered by event type', function () {
    Template::where('slug', 'minimal')->update(['is_active' => false]);
    Template::factory()->create(['slug' => 'sadece-dogum-gunu', 'event_types' => ['birthday'], 'sort_order' => 99]);

    $all = $this->getJson('/api/v1/templates')->assertOk()->json();
    expect(array_column($all, 'slug'))->toBe(['klasik', 'modern', 'bahar', 'altin-varak', 'gece-mavisi', 'sadece-dogum-gunu'])
        ->and(collect($all)->firstWhere('slug', 'altin-varak')['is_premium'])->toBeTrue()
        ->and(collect($all)->firstWhere('slug', 'klasik')['config']['frame'])->toBe('double');

    $wedding = $this->getJson('/api/v1/templates?event_type=wedding')->json();
    expect(array_column($wedding, 'slug'))->not->toContain('sadece-dogum-gunu');

    $this->getJson('/api/v1/templates?event_type=party')->assertJsonValidationErrors('event_type');
});

it('returns 404 before an invitation exists', function () {
    $this->getJson("/api/v1/events/{$this->event->id}/invitation")->assertNotFound();
});

it('creates an invitation with event type defaults', function () {
    $this->putJson("/api/v1/events/{$this->event->id}/invitation", ['template_id' => $this->free->id])
        ->assertCreated()
        ->assertJsonPath('template.slug', 'klasik')
        ->assertJsonPath('texts', [
            'label' => 'KINA GECESİ DAVETİ',
            'intro' => '',
            'names' => 'Elif & Emre',
            'message' => 'kına gecemizde sizi de aramızda görmekten mutluluk duyarız.',
        ])
        ->assertJsonPath('envelope_color', 'sage')
        ->assertJsonPath('seal_color', 'bordo')
        ->assertJsonPath('first_sent_at', null);

    $this->getJson("/api/v1/events/{$this->event->id}/invitation")->assertOk()->assertJsonPath('template.id', $this->free->id);
    $this->getJson("/api/v1/events/{$this->event->id}")->assertJsonPath('invitation_status', 'draft');
});

it('updates texts partially and keeps one invitation per event', function () {
    $url = "/api/v1/events/{$this->event->id}/invitation";
    $this->putJson($url, ['template_id' => $this->free->id])->assertCreated();

    $this->putJson($url, [
        'template_id' => $this->free->id,
        'texts' => ['intro' => 'Yılmaz ve Demir aileleri olarak'],
        'envelope_color' => 'navy',
        'seal_text' => 'E&E',
        'rsvp_deadline' => '2027-06-01',
    ])
        ->assertOk()
        ->assertJsonPath('texts.intro', 'Yılmaz ve Demir aileleri olarak')
        ->assertJsonPath('texts.label', 'KINA GECESİ DAVETİ')
        ->assertJsonPath('envelope_color', 'navy')
        ->assertJsonPath('seal_text', 'E&E')
        ->assertJsonPath('rsvp_deadline', '2027-06-01');

    expect(Invitation::count())->toBe(1);
});

it('validates invitation fields', function () {
    $this->putJson("/api/v1/events/{$this->event->id}/invitation", [
        'template_id' => 9999,
        'texts' => ['label' => str_repeat('x', 61), 'extra' => 'x'],
        'envelope_color' => 'red',
        'seal_color' => 'blue',
        'seal_text' => 'ABCDE',
        'rsvp_deadline' => '2027-06-13',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['template_id', 'texts', 'texts.label', 'envelope_color', 'seal_color', 'seal_text', 'rsvp_deadline']);
});

it('rejects inactive templates', function () {
    $this->free->update(['is_active' => false]);

    $this->putJson("/api/v1/events/{$this->event->id}/invitation", ['template_id' => $this->free->id])
        ->assertJsonValidationErrors('template_id');
});

it('requires the pack for premium templates and colors', function (array $payload) {
    $this->putJson("/api/v1/events/{$this->event->id}/invitation", [
        'template_id' => $this->free->id,
        ...$payload,
    ])
        ->assertStatus(402)
        ->assertJsonPath('code', 'pack_required');

    expect(Invitation::count())->toBe(0);
})->with([
    'premium template' => fn () => ['template_id' => $this->premium->id],
    'premium envelope' => [['envelope_color' => 'blush']],
    'premium seal' => [['seal_color' => 'gold']],
]);

it('rejects premium on an existing free invitation without changing it', function () {
    $url = "/api/v1/events/{$this->event->id}/invitation";
    $this->putJson($url, ['template_id' => $this->free->id])->assertCreated();

    $this->putJson($url, ['template_id' => $this->premium->id])->assertStatus(402);

    expect(Invitation::sole()->template_id)->toBe($this->free->id);
});

it('allows premium templates and colors with the pack', function () {
    $this->event->update(['pack_purchased_at' => now()]);

    $this->putJson("/api/v1/events/{$this->event->id}/invitation", [
        'template_id' => $this->premium->id,
        'envelope_color' => 'blush',
        'seal_color' => 'gold',
    ])
        ->assertCreated()
        ->assertJsonPath('template.slug', 'altin-varak')
        ->assertJsonPath('template.is_premium', true);
});
