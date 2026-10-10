<?php

use App\Enums\EventCreator;
use App\Enums\ReservationStatus;
use App\Enums\RsvpStatus;
use App\Events\SalonEventLinked;
use App\Filament\Salon\Pages\Auth\SalonLogin;
use App\Filament\Salon\Pages\ReservationCalendar;
use App\Filament\Salon\Pages\SalonProfile;
use App\Filament\Salon\Resources\Reservations\Pages\CreateReservation;
use App\Filament\Salon\Resources\Reservations\Pages\EditReservation;
use App\Filament\Salon\Resources\Reservations\Pages\ListReservations;
use App\Filament\Salon\Resources\Reservations\Pages\ViewReservation;
use App\Filament\Salon\Resources\Reservations\ReservationResource;
use App\Filament\Salon\Resources\SalonUsers\Pages\ManageSalonUsers;
use App\Filament\Salon\Resources\SalonUsers\SalonUserResource;
use App\Filament\Salon\Widgets\SalonStats;
use App\Filament\Salon\Widgets\UpcomingEvents;
use App\Models\Event as EventModel;
use App\Models\Invitation;
use App\Models\Recipient;
use App\Models\Reservation;
use App\Models\Salon;
use App\Models\SalonUser;
use App\Models\User;
use App\Notifications\SalonPasswordSetup;
use App\Services\ReservationService;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('salon');

    $this->salon = Salon::factory()->create([
        'name' => 'Gül Bahçesi',
        'address' => 'Atatürk Bulvarı 1',
        'district' => 'Şahinbey',
        'city' => 'Gaziantep',
    ]);
    $this->owner = SalonUser::factory()->for($this->salon)->create([
        'email' => 'sahip@gulbahcesi.test',
        'phone' => '+905331112233',
    ]);
});

function reservationFor(Salon $salon, array $reservation = [], array $event = []): Reservation
{
    $eventModel = EventModel::factory()->fromSalon()->create(['salon_id' => $salon->id, ...$event]);

    return Reservation::factory()->create([
        'event_id' => $eventModel->id,
        'salon_id' => $salon->id,
        'contact_phone' => $eventModel->couple_phone,
        ...$reservation,
    ]);
}

function reservationForm(array $overrides = []): array
{
    return [
        'title' => 'Elif & Emre',
        'type' => 'wedding',
        'date' => '2027-06-12',
        'start_time' => '19:00',
        'end_time' => '23:30',
        'status' => 'confirmed',
        'contact_name' => 'Elif Yılmaz',
        'couple_phone' => '0532 111 22 33',
        'total_amount' => '150000',
        'deposit_amount' => '30000',
        'paid_amount' => '30000.50',
        'notes' => null,
        ...$overrides,
    ];
}

describe('login', function () {
    it('accepts email or phone in the single login field', function (string $login) {
        Livewire::test(SalonLogin::class)
            ->fillForm(['login' => $login, 'password' => 'password'])
            ->call('authenticate')
            ->assertHasNoFormErrors();

        $this->assertAuthenticatedAs($this->owner, 'salon');
        expect($this->owner->refresh()->last_login_at)->not->toBeNull();
    })->with([
        'email' => 'sahip@gulbahcesi.test',
        'email upper case' => 'Sahip@GulBahcesi.test',
        'phone' => '0533 111 22 33',
        'phone e164' => '+905331112233',
    ]);

    it('rejects wrong credentials, inactive users and inactive salons', function (Closure $setup, string $login, string $password) {
        $setup($this);

        Livewire::test(SalonLogin::class)
            ->fillForm(['login' => $login, 'password' => $password])
            ->call('authenticate')
            ->assertHasFormErrors(['login']);

        $this->assertGuest('salon');
    })->with([
        'wrong password' => [fn () => null, 'sahip@gulbahcesi.test', 'yanlis'],
        'unknown phone' => [fn () => null, '0532 000 00 00', 'password'],
        'invalid phone' => [fn () => null, '12345', 'password'],
        'inactive user' => [fn ($t) => $t->owner->update(['is_active' => false]), 'sahip@gulbahcesi.test', 'password'],
        'inactive salon' => [fn ($t) => $t->salon->update(['is_active' => false]), 'sahip@gulbahcesi.test', 'password'],
        'password not set yet' => [fn ($t) => $t->owner->forceFill(['password' => null])->save(), 'sahip@gulbahcesi.test', 'password'],
    ]);

    it('keeps salon users out of the admin panel', function () {
        $this->actingAs($this->owner, 'salon')->get('/admin')->assertRedirect('/admin/login');
    });

    it('offers password reset by email', function () {
        $this->get('/salon/password-reset/request')->assertOk();
    });
});

describe('reservations', function () {
    beforeEach(fn () => $this->actingAs($this->owner, 'salon'));

    it('creates the event from a reservation and links a registered couple', function () {
        Event::fake([SalonEventLinked::class]);
        $couple = User::factory()->create(['phone' => '+905321112233']);

        Livewire::test(CreateReservation::class)
            ->fillForm(reservationForm())
            ->call('create')
            ->assertHasNoFormErrors();

        $reservation = Reservation::sole();
        $event = $reservation->event;

        expect($reservation->salon_id)->toBe($this->salon->id)
            ->and($reservation->status)->toBe(ReservationStatus::Confirmed)
            ->and($reservation->total_amount)->toBe(15_000_000)
            ->and($reservation->paid_amount)->toBe(3_000_050)
            ->and($reservation->contact_phone)->toBe('+905321112233')
            ->and($event->salon_id)->toBe($this->salon->id)
            ->and($event->created_by)->toBe(EventCreator::Salon)
            ->and($event->title)->toBe('Elif & Emre')
            ->and($event->couple_phone)->toBe('+905321112233')
            ->and($event->owner_user_id)->toBe($couple->id)
            ->and($event->venue_name)->toBe('Gül Bahçesi')
            ->and($event->venue_address)->toBe('Atatürk Bulvarı 1, Şahinbey, Gaziantep')
            // 19:00 İstanbul = 16:00 UTC
            ->and($event->starts_at->toIso8601String())->toBe('2027-06-12T16:00:00+00:00')
            ->and($event->ends_at->toIso8601String())->toBe('2027-06-12T20:30:00+00:00');

        Event::assertDispatched(SalonEventLinked::class, fn ($e) => $e->event->is($event));
    });

    it('leaves the event unlinked until the couple registers', function () {
        Event::fake([SalonEventLinked::class]);

        Livewire::test(CreateReservation::class)
            ->fillForm(reservationForm(['end_time' => '01:00']))
            ->call('create')
            ->assertHasNoFormErrors();

        $event = EventModel::sole();
        expect($event->owner_user_id)->toBeNull()
            ->and($event->ends_at->toIso8601String())->toBe('2027-06-12T22:00:00+00:00');

        Event::assertNotDispatched(SalonEventLinked::class);
    });

    it('validates the couple phone', function () {
        Livewire::test(CreateReservation::class)
            ->fillForm(reservationForm(['couple_phone' => '123']))
            ->call('create')
            ->assertHasFormErrors(['couple_phone']);

        expect(Reservation::count())->toBe(0);
    });

    it('updates the event when the reservation is edited', function () {
        $reservation = reservationFor($this->salon);

        Livewire::test(EditReservation::class, ['record' => $reservation->getRouteKey()])
            ->assertFormSet(['couple_phone' => $reservation->contact_phone])
            ->fillForm(reservationForm(['title' => 'Yeni Başlık', 'start_time' => '18:00']))
            ->call('save')
            ->assertHasNoFormErrors();

        $event = $reservation->event->refresh();
        expect($event->title)->toBe('Yeni Başlık')
            ->and($event->starts_at->toIso8601String())->toBe('2027-06-12T15:00:00+00:00')
            ->and(EventModel::count())->toBe(1);
    });

    it('cancels the event when the reservation is cancelled', function () {
        $reservation = reservationFor($this->salon);

        Livewire::test(ViewReservation::class, ['record' => $reservation->getRouteKey()])
            ->callAction('cancel');

        expect($reservation->refresh()->status)->toBe(ReservationStatus::Cancelled)
            ->and($reservation->event->refresh()->cancelled_at)->not->toBeNull();
    });

    it('warns and asks for confirmation on overlapping confirmed reservations', function () {
        reservationFor($this->salon, [], [
            'title' => 'Ayşe & Can',
            'starts_at' => '2027-06-12 17:00:00',
            'ends_at' => '2027-06-12 21:00:00',
        ]);

        $page = Livewire::test(CreateReservation::class)
            ->fillForm(reservationForm())
            ->call('create')
            ->assertActionMounted('confirmOverlap');

        expect(Reservation::count())->toBe(1)
            ->and($page->get('overlapMessage'))->toContain('Ayşe & Can');

        $page->callMountedAction()->assertHasNoFormErrors();

        expect(Reservation::count())->toBe(2);
    });

    it('does not warn for option, cancelled or other salon reservations', function () {
        reservationFor($this->salon, ['status' => ReservationStatus::Option], [
            'starts_at' => '2027-06-12 17:00:00', 'ends_at' => '2027-06-12 21:00:00',
        ]);
        reservationFor(Salon::factory()->create(), [], [
            'starts_at' => '2027-06-12 17:00:00', 'ends_at' => '2027-06-12 21:00:00',
        ]);

        Livewire::test(CreateReservation::class)
            ->fillForm(reservationForm())
            ->call('create')
            ->assertActionNotMounted('confirmOverlap')
            ->assertHasNoFormErrors();

        expect(Reservation::where('salon_id', $this->salon->id)->count())->toBe(2);
    });

    it('shows the rsvp summary only when the couple shares headcount', function () {
        $reservation = reservationFor($this->salon);
        $invitation = Invitation::factory()->create(['event_id' => $reservation->event_id]);
        Recipient::factory()->sentByLink()->for($invitation)->create([
            'opened_at' => now(), 'rsvp_status' => RsvpStatus::Attending, 'guest_count' => 2,
        ]);
        Recipient::factory()->sentByLink()->for($invitation)->create(['opened_at' => now()]);
        Recipient::factory()->sentByLink()->for($invitation)->create();

        $url = ReservationResource::getUrl('view', ['record' => $reservation]);

        $this->get($url)->assertOk()->assertSee('2 kişi')->assertDontSee('Çift kişi sayısını paylaşmıyor');

        $reservation->event->update(['share_headcount_with_salon' => false]);

        $this->get($url)->assertOk()->assertSee('Çift kişi sayısını paylaşmıyor')->assertDontSee('2 kişi');
    });

    it('shows the invite-the-couple box with QR and WhatsApp link', function () {
        $reservation = reservationFor($this->salon, ['contact_phone' => '+905321112233'], ['couple_phone' => '+905321112233']);

        $this->get(ReservationResource::getUrl('view', ['record' => $reservation]))
            ->assertOk()
            ->assertSee('<svg', false)
            ->assertSee('https://wa.me/905321112233?text=', false)
            ->assertSee(url('/indir'));
    });
});

describe('salon isolation', function () {
    beforeEach(function () {
        $this->actingAs($this->owner, 'salon');
        $this->mine = reservationFor($this->salon);
        $this->theirs = reservationFor(Salon::factory()->create());
    });

    it('lists only own reservations', function () {
        Livewire::test(ListReservations::class)
            ->assertCanSeeTableRecords([$this->mine])
            ->assertCanNotSeeTableRecords([$this->theirs]);
    });

    it('cannot open another salon reservation by URL', function (string $page) {
        $this->get(ReservationResource::getUrl($page, ['record' => $this->theirs]))->assertNotFound();
        $this->get(ReservationResource::getUrl($page, ['record' => $this->mine]))->assertOk();
    })->with(['view', 'edit']);

    it('cannot reach another salon reservation through Livewire', function () {
        Livewire::test(EditReservation::class, ['record' => $this->theirs->getRouteKey()])->assertNotFound();
    });

    it('shows only own reservations in the calendar', function () {
        $this->theirs->event->update(['title' => 'Başka Salon Düğünü', 'starts_at' => $this->mine->event->starts_at]);
        $day = $this->mine->event->starts_at->timezone(ReservationService::DISPLAY_TIMEZONE);

        Livewire::test(ReservationCalendar::class, ['month' => $day->format('Y-m')])
            ->call('selectDay', $day->toDateString())
            ->assertSee($this->mine->event->title)
            ->assertDontSee('Başka Salon Düğünü')
            ->assertSee('kesin');
    });

    it('cannot see or edit another salon users', function () {
        $stranger = SalonUser::factory()->create();

        Livewire::test(ManageSalonUsers::class)
            ->assertCanSeeTableRecords([$this->owner])
            ->assertCanNotSeeTableRecords([$stranger]);
    });
});

describe('owner-only pages', function () {
    it('blocks staff from profile and users pages', function () {
        $staff = SalonUser::factory()->staff()->for($this->salon)->create();
        $this->actingAs($staff, 'salon');

        $this->get(SalonProfile::getUrl())->assertForbidden();
        $this->get(SalonUserResource::getUrl())->assertForbidden();
        $this->get(ReservationResource::getUrl())->assertOk();
    });

    it('lets the owner edit the salon profile', function () {
        $this->actingAs($this->owner, 'salon');

        $this->get(SalonProfile::getUrl())->assertOk();

        Livewire::test(SalonProfile::class)
            ->fillForm(['name' => 'Gül Bahçesi Davet', 'phone' => '0342 222 11 00', 'capacity' => 800])
            ->call('save')
            ->assertHasNoFormErrors();

        expect($this->salon->refresh())
            ->name->toBe('Gül Bahçesi Davet')
            ->phone->toBe('+903422221100')
            ->capacity->toBe(800);
    });

    it('lets the owner add and deactivate staff', function () {
        Notification::fake();
        $this->actingAs($this->owner, 'salon');

        Livewire::test(ManageSalonUsers::class)
            ->callAction('create', ['name' => 'Personel', 'email' => 'personel@test.test', 'phone' => '0533 999 88 77'])
            ->assertHasNoFormErrors();

        $staff = SalonUser::where('email', 'personel@test.test')->sole();
        expect($staff->salon_id)->toBe($this->salon->id)
            ->and($staff->isOwner())->toBeFalse()
            ->and($staff->phone)->toBe('+905339998877')
            ->and($staff->password)->toBeNull();

        Notification::assertSentTo($staff, SalonPasswordSetup::class);

        Livewire::test(ManageSalonUsers::class)->callTableAction('toggleActive', $staff);

        expect($staff->refresh()->is_active)->toBeFalse();
    });
});

it('renders dashboard and calendar', function () {
    $this->actingAs($this->owner, 'salon');
    reservationFor($this->salon, [], ['title' => 'Yaklaşan Düğün', 'starts_at' => now()->addDays(3)]);

    $this->get('/salon')->assertOk();
    Livewire::test(SalonStats::class)->assertSee('Bu ayın rezervasyonları');
    Livewire::test(UpcomingEvents::class)->assertSee('Yaklaşan Düğün')->assertSee('Gönderilmedi');
    $this->get(ReservationCalendar::getUrl())->assertOk()->assertSee('Takvim');
});
