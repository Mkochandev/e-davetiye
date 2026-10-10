<?php

use App\Enums\RecipientChannel;
use App\Enums\RsvpStatus;
use App\Models\Event;
use App\Models\OtpRequest;
use App\Models\Recipient;
use App\Models\User;
use App\Services\OtpService;
use App\Services\PhoneNumber;
use App\Services\Sms\SmsSender;
use Tests\Fakes\FakeSmsSender;

beforeEach(function () {
    $this->sms = new FakeSmsSender;
    app()->instance(SmsSender::class, $this->sms);
});

function requestCode(string $phone = '5321112233'): string
{
    test()->postJson('/api/v1/auth/otp', ['phone' => $phone])->assertNoContent();

    return test()->sms->lastCodeFor(app(PhoneNumber::class)->normalize($phone));
}

function verifyCode(string $code, string $phone = '5321112233')
{
    return test()->postJson('/api/v1/auth/verify', [
        'phone' => $phone,
        'code' => $code,
        'device_name' => 'Pixel 8',
    ]);
}

it('logs a new user in with the SMS code', function () {
    $code = requestCode();

    $response = verifyCode($code)
        ->assertOk()
        ->assertJsonPath('is_new', true)
        ->assertJsonPath('user.phone', '+905321112233')
        ->assertJsonPath('user.name', null);

    $user = User::sole();
    expect($user->phone_hash)->toBe(hash('sha256', '+905321112233'))
        ->and($user->tokens()->sole()->name)->toBe('Pixel 8')
        ->and(OtpRequest::sole()->consumed_at)->not->toBeNull();

    $this->withToken($response->json('token'))
        ->getJson('/api/v1/me')
        ->assertOk()
        ->assertJsonPath('id', $user->id);
});

it('logs an existing user in without marking them new', function () {
    $user = User::factory()->create(['phone' => '+905321112233']);

    verifyCode(requestCode())
        ->assertOk()
        ->assertJsonPath('is_new', false)
        ->assertJsonPath('user.id', $user->id);

    expect(User::count())->toBe(1);
});

it('rejects a wrong code', function () {
    $code = requestCode();
    $wrong = $code === '000000' ? '000001' : '000000';

    verifyCode($wrong)
        ->assertUnprocessable()
        ->assertJsonPath('errors.code.0', 'Kod hatalı. Lütfen tekrar deneyin.');

    expect(User::count())->toBe(0)
        ->and(OtpRequest::sole()->attempts)->toBe(1);
});

it('rejects an expired code', function () {
    $code = requestCode();

    $this->travel(5)->minutes();
    $this->travel(1)->seconds();

    verifyCode($code)
        ->assertUnprocessable()
        ->assertJsonPath('errors.code.0', 'Kodun süresi doldu. Lütfen yeni kod isteyin.');

    expect(User::count())->toBe(0);
});

it('invalidates the code after 5 wrong attempts', function () {
    $code = requestCode();
    $wrong = $code === '000000' ? '000001' : '000000';

    foreach (range(1, 5) as $i) {
        verifyCode($wrong)->assertJsonPath('errors.code.0', 'Kod hatalı. Lütfen tekrar deneyin.');
    }

    verifyCode($code)
        ->assertUnprocessable()
        ->assertJsonPath('errors.code.0', 'Çok fazla hatalı deneme yapıldı. Lütfen yeni kod isteyin.');

    expect(User::count())->toBe(0);
});

it('does not accept a code twice', function () {
    $code = requestCode();

    verifyCode($code)->assertOk();
    verifyCode($code)
        ->assertUnprocessable()
        ->assertJsonPath('errors.code.0', 'Geçerli bir kod bulunamadı. Lütfen yeni kod isteyin.');
});

it('only accepts the latest code for the number', function () {
    $first = requestCode();
    $this->travel(61)->seconds();
    $second = requestCode();

    if ($first !== $second) {
        verifyCode($first)->assertUnprocessable();
    }

    verifyCode($second)->assertOk();
});

it('rejects verification without a requested code', function () {
    verifyCode('654321')->assertUnprocessable();
});

it('accepts the master code in the testing environment', function () {
    verifyCode('123456')->assertOk()->assertJsonPath('is_new', true);
});

it('validates the verify payload', function () {
    $this->postJson('/api/v1/auth/verify', ['phone' => '123', 'code' => '12'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['phone', 'code', 'device_name']);
});

it('links pending envelopes and salon events to a new user on verify', function () {
    $phone = '+905321112233';

    // Web linkinden LCV vermiş zarf.
    $linked = Recipient::factory()->sentByLink()->create([
        'phone' => $phone,
        'phone_hash' => hash('sha256', $phone),
        'rsvp_status' => RsvpStatus::Attending,
        'guest_count' => 2,
        'responded_at' => now(),
    ]);
    $salonEvent = Event::factory()->fromSalon($phone)->create();

    // Başkasına ait kayıtlar dokunulmadan kalmalı.
    $otherUser = User::factory()->create();
    $alreadyOwned = Recipient::factory()->sentByLink()->create([
        'phone' => $phone,
        'phone_hash' => hash('sha256', $phone),
        'user_id' => $otherUser->id,
    ]);
    $ownedEvent = Event::factory()->fromSalon($phone)->create(['owner_user_id' => $otherUser->id]);
    $strangerEnvelope = Recipient::factory()->sentByLink()->create();
    $strangerEvent = Event::factory()->fromSalon('+905329998877')->create();

    $userId = verifyCode(requestCode())->assertOk()->json('user.id');

    $linked->refresh();
    expect($linked->user_id)->toBe($userId)
        ->and($linked->channel)->toBe(RecipientChannel::Link)
        ->and($linked->rsvp_status)->toBe(RsvpStatus::Attending)
        ->and($linked->guest_count)->toBe(2)
        ->and($salonEvent->refresh()->owner_user_id)->toBe($userId)
        ->and($alreadyOwned->refresh()->user_id)->toBe($otherUser->id)
        ->and($ownedEvent->refresh()->owner_user_id)->toBe($otherUser->id)
        ->and($strangerEnvelope->refresh()->user_id)->toBeNull()
        ->and($strangerEvent->refresh()->owner_user_id)->toBeNull();
});

it('links records created after registration on the next login', function () {
    $user = User::factory()->create(['phone' => '+905321112233']);
    $event = Event::factory()->fromSalon('+905321112233')->create();

    verifyCode(requestCode())->assertOk();

    expect($event->refresh()->owner_user_id)->toBe($user->id);
});

it('never accepts the master code outside local and testing', function (string $env) {
    app()->detectEnvironment(fn () => $env);

    expect(OtpService::masterCodeEnabled())->toBeFalse();

    verifyCode('123456')->assertUnprocessable();
    expect(User::count())->toBe(0);
})->with(['production', 'staging']);
