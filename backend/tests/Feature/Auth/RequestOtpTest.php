<?php

use App\Models\OtpRequest;
use App\Models\User;
use App\Services\Sms\SmsSender;
use Illuminate\Support\Facades\Hash;
use Tests\Fakes\FakeSmsSender;

beforeEach(function () {
    $this->sms = new FakeSmsSender;
    app()->instance(SmsSender::class, $this->sms);
});

it('sends a 6 digit code and stores only its hash', function () {
    $this->postJson('/api/v1/auth/otp', ['phone' => '0532 111 22 33'])->assertNoContent();

    $code = $this->sms->lastCodeFor('+905321112233');
    $otp = OtpRequest::sole();

    expect($code)->toMatch('/^\d{6}$/')
        ->and($otp->phone)->toBe('+905321112233')
        ->and($otp->code_hash)->not->toBe($code)
        ->and(Hash::check($code, $otp->code_hash))->toBeTrue()
        ->and($otp->expires_at->diffInSeconds(now()->addMinutes(5), true))->toBeLessThan(5);
});

it('responds the same whether or not the number is registered', function () {
    User::factory()->create(['phone' => '+905321112233']);

    $registered = $this->postJson('/api/v1/auth/otp', ['phone' => '5321112233']);
    $unknown = $this->postJson('/api/v1/auth/otp', ['phone' => '5321112234']);

    $registered->assertNoContent();
    $unknown->assertNoContent();
    expect($registered->getContent())->toBe($unknown->getContent());
});

it('rejects an invalid phone with a Turkish validation message', function () {
    $this->postJson('/api/v1/auth/otp', ['phone' => '12345'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.phone.0', 'Geçerli bir telefon numarası girin.');

    $this->postJson('/api/v1/auth/otp', [])
        ->assertUnprocessable()
        ->assertJsonPath('message', 'telefon numarası alanı zorunludur.');

    expect($this->sms->sent)->toBeEmpty();
});

it('sends codes only to mobile numbers', function () {
    // 0342: Gaziantep sabit hattı.
    $this->postJson('/api/v1/auth/otp', ['phone' => '0342 111 22 33'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.phone.0', 'Giriş kodu yalnız cep telefonu numarasına gönderilebilir.');

    expect($this->sms->sent)->toBeEmpty();
});

it('allows one code per number every 60 seconds', function () {
    $this->postJson('/api/v1/auth/otp', ['phone' => '5321112233'])->assertNoContent();

    $this->postJson('/api/v1/auth/otp', ['phone' => '5321112233'])
        ->assertTooManyRequests()
        ->assertHeader('Retry-After')
        ->assertJsonPath('message', fn ($m) => str_contains($m, 'saniye bekleyin'));

    $this->travel(61)->seconds();

    $this->postJson('/api/v1/auth/otp', ['phone' => '5321112233'])->assertNoContent();
    expect($this->sms->sent)->toHaveCount(2);
});

it('allows at most 5 codes per number per hour', function () {
    foreach (range(1, 5) as $i) {
        $this->postJson('/api/v1/auth/otp', ['phone' => '5321112233'])->assertNoContent();
        $this->travel(61)->seconds();
    }

    $this->postJson('/api/v1/auth/otp', ['phone' => '5321112233'])->assertTooManyRequests();

    $this->travel(1)->hour();

    $this->postJson('/api/v1/auth/otp', ['phone' => '5321112233'])->assertNoContent();
});

it('allows at most 20 requests per IP per hour', function () {
    foreach (range(1, 20) as $i) {
        $this->postJson('/api/v1/auth/otp', ['phone' => sprintf('53200%05d', $i)])->assertNoContent();
    }

    $this->postJson('/api/v1/auth/otp', ['phone' => '5329999999'])
        ->assertTooManyRequests()
        ->assertJsonPath('message', 'Çok fazla istek gönderildi. Lütfen biraz sonra tekrar deneyin.');

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.2'])
        ->postJson('/api/v1/auth/otp', ['phone' => '5329999999'])
        ->assertNoContent();
});
