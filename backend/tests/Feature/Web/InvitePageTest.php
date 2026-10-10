<?php

use App\Enums\RecipientChannel;
use App\Enums\RsvpStatus;
use App\Jobs\SendPushNotification;
use App\Models\Event;
use App\Models\Invitation;
use App\Models\Recipient;
use App\Models\Template;
use App\Services\PhoneNumber;
use Illuminate\Support\Facades\Queue;

beforeEach(function () {
    Queue::fake();
    $this->event = Event::factory()->create([
        'title' => 'Elif & Emre',
        'starts_at' => '2027-06-12 16:00:00',
        'venue_name' => 'Gül Düğün Salonu',
    ]);
    $this->invitation = Invitation::factory()->create(['event_id' => $this->event->id]);
    $this->recipient = Recipient::factory()->create([
        'invitation_id' => $this->invitation->id,
        'phone' => '+905361112233',
        'channel' => RecipientChannel::Link,
        'sent_at' => now(),
    ]);
    $this->url = "/z/{$this->recipient->token}";
});

it('renders the invitation card with the template and event, not indexed', function () {
    $this->get($this->url)
        ->assertOk()
        ->assertHeader('X-Robots-Tag', 'noindex, nofollow')
        ->assertSee('<meta name="robots" content="noindex, nofollow">', false)
        ->assertSeeInOrder(['DÜĞÜN DAVETİ', 'Elif &amp; Emre', 'düğünümüzde', '12 Haziran 2027', 'Cumartesi · 19:00', 'Gül Düğün Salonu'], false)
        ->assertSee('icard--frame-double', false)
        ->assertSee('--accent: #8A1F3D', false)
        ->assertSee('--env-flap: #A1AC9C', false)
        ->assertSee('Katılıyorum');
});

it('serves fonts from our own server only', function () {
    $html = $this->get($this->url)->assertOk()->getContent();

    expect($html)->toContain(asset('fonts/fonts.css'))
        ->not->toContain('fonts.googleapis.com')
        ->not->toContain('fonts.gstatic.com');

    $css = file_get_contents(public_path('fonts/fonts.css'));
    preg_match_all("/url\('([^']+)'\)/", $css, $files);
    expect($files[1])->toHaveCount(12);
    foreach ($files[1] as $file) {
        expect(public_path("fonts/{$file}"))->toBeFile();
    }
});

it('follows the template config', function () {
    $template = Template::factory()->create(['config' => [
        ...Template::factory()->make()->config,
        'paper' => '#1D2340',
        'frame' => 'none',
        'ornament' => 'top-band',
    ]]);
    $this->invitation->update(['template_id' => $template->id]);

    $this->get($this->url)
        ->assertOk()
        ->assertSee('--paper: #1D2340', false)
        ->assertSee('icard--frame-none', false)
        ->assertSee('class="icard__band"', false)
        ->assertDontSee('class="icard__dot-rule"', false);
});

it('does not mark opened on page load, only when the seal is tapped', function () {
    $this->get($this->url)->assertOk();
    expect($this->recipient->refresh()->opened_at)->toBeNull();

    $this->post("{$this->url}/open")->assertNoContent();
    $openedAt = $this->recipient->refresh()->opened_at;

    $this->travel(1)->hour();
    $this->post("{$this->url}/open")->assertNoContent();

    expect($openedAt)->not->toBeNull()
        ->and($this->recipient->refresh()->opened_at->equalTo($openedAt))->toBeTrue();
});

it('returns 404 for an unknown or unsent token', function () {
    $unsent = Recipient::factory()->create(['invitation_id' => $this->invitation->id]);

    $this->get('/z/'.str_repeat('a', 40))->assertNotFound()->assertSee('Sayfa bulunamadı');
    $this->get("/z/{$unsent->token}")->assertNotFound();
    $this->post("/z/{$unsent->token}/rsvp", ['status' => 'not_attending'])->assertNotFound();
    $this->get('/z/kisa')->assertNotFound();
});

it('takes an rsvp by token and shows the thank-you screen', function () {
    $this->post("{$this->url}/rsvp", ['status' => 'attending', 'guest_count' => 2])
        ->assertRedirect($this->url);

    $this->recipient->refresh();
    expect($this->recipient->rsvp_status)->toBe(RsvpStatus::Attending)
        ->and($this->recipient->guest_count)->toBe(2)
        ->and($this->recipient->opened_at)->not->toBeNull();

    $this->followingRedirects()
        ->post("{$this->url}/rsvp", ['status' => 'attending', 'guest_count' => 2])
        ->assertOk()
        ->assertSee('Teşekkürler')
        ->assertSee('2 kişi')
        ->assertSee('Google Play');

    // Teşekkür ekranı bir kez gösterilir; sonra davetiye mevcut yanıtla açılır.
    $this->get($this->url)->assertOk()->assertSee('Katılıyorum · 2 kişi')->assertSee('Yanıtı güncelle');

    Queue::assertPushed(SendPushNotification::class, fn ($job) => $job->user->is($this->event->owner));
});

it('rejects a web rsvp after the deadline', function () {
    $this->invitation->update(['rsvp_deadline' => now('Europe/Istanbul')->subDay()->toDateString()]);

    $this->get($this->url)->assertOk()->assertSee('LCV süresi doldu');

    $this->from($this->url)
        ->post("{$this->url}/rsvp", ['status' => 'attending', 'guest_count' => 1])
        ->assertRedirect($this->url)
        ->assertSessionHasErrors('status');

    expect($this->recipient->refresh()->rsvp_status)->toBe(RsvpStatus::Pending);
});

it('shows a cancelled event message', function () {
    $this->event->update(['cancelled_at' => now()]);

    $this->get($this->url)
        ->assertOk()
        ->assertSee('Bu etkinlik iptal edildi')
        ->assertDontSee('Katılıyorum');

    $this->post("{$this->url}/rsvp", ['status' => 'not_attending'])->assertSessionHasErrors('status');
});

it('keeps the web rsvp when the guest later registers', function () {
    $this->post("{$this->url}/rsvp", ['status' => 'attending', 'guest_count' => 3]);

    $this->postJson('/api/v1/auth/otp', ['phone' => '05361112233'])->assertNoContent();
    $token = $this->postJson('/api/v1/auth/verify', ['phone' => '05361112233', 'code' => '123456', 'device_name' => 'Pixel'])
        ->assertOk()
        ->json('token');

    $this->withToken($token)->getJson('/api/v1/inbox')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonPath('0.id', $this->recipient->id)
        ->assertJsonPath('0.rsvp_status', 'attending');

    $this->recipient->refresh();
    expect($this->recipient->guest_count)->toBe(3)
        ->and($this->recipient->channel)->toBe(RecipientChannel::Link)
        ->and($this->recipient->user->phone)->toBe(app(PhoneNumber::class)->normalize('05361112233'));
});

it('serves the static pages', function (string $path, string $text) {
    $this->get($path)->assertOk()->assertSee($text);
})->with([
    ['/indir', 'App Store'],
    ['/aydinlatma-metni', '[Hukukçu onayı bekliyor]'],
    ['/gizlilik', '[Hukukçu onayı bekliyor]'],
    ['/kullanim-kosullari', '[Hukukçu onayı bekliyor]'],
]);
