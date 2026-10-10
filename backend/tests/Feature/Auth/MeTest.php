<?php

use App\Models\Device;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('requires authentication', function () {
    $this->getJson('/api/v1/me')
        ->assertUnauthorized()
        ->assertJsonPath('message', 'Oturumunuz sona erdi. Lütfen tekrar giriş yapın.');

    $this->patchJson('/api/v1/me', ['name' => 'Ayşe'])->assertUnauthorized();
    $this->postJson('/api/v1/auth/logout')->assertUnauthorized();
    $this->postJson('/api/v1/devices', [])->assertUnauthorized();
});

it('returns only the authenticated user', function () {
    $me = User::factory()->create();
    User::factory()->create();

    Sanctum::actingAs($me);

    $this->getJson('/api/v1/me')
        ->assertOk()
        ->assertExactJson([
            'id' => $me->id,
            'phone' => $me->phone,
            'name' => $me->name,
            'kvkk_accepted_at' => $me->kvkk_accepted_at->toJSON(),
            'kvkk_version' => $me->kvkk_version,
        ]);
});

it('completes the profile with name and consent', function () {
    $user = User::factory()->incomplete()->create();
    Sanctum::actingAs($user);

    $this->patchJson('/api/v1/me', ['name' => '  Ayşe Yılmaz ', 'kvkk_accepted' => true])
        ->assertOk()
        ->assertJsonPath('name', 'Ayşe Yılmaz')
        ->assertJsonPath('kvkk_version', config('zarf.kvkk_version'));

    expect($user->refresh()->kvkk_accepted_at)->not->toBeNull();
});

it('requires consent before the first profile update', function () {
    Sanctum::actingAs(User::factory()->incomplete()->create());

    $this->patchJson('/api/v1/me', ['name' => 'Ayşe Yılmaz'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('kvkk_accepted');

    $this->patchJson('/api/v1/me', ['name' => 'Ayşe Yılmaz', 'kvkk_accepted' => false])
        ->assertUnprocessable()
        ->assertJsonPath('errors.kvkk_accepted.0', 'açık rıza onayı onaylanmalıdır.');
});

it('lets a consented user rename without resending consent', function () {
    $user = User::factory()->create();
    $acceptedAt = $user->kvkk_accepted_at;
    Sanctum::actingAs($user);

    $this->patchJson('/api/v1/me', ['name' => 'Yeni Ad'])->assertOk()->assertJsonPath('name', 'Yeni Ad');

    expect($user->refresh()->kvkk_accepted_at->equalTo($acceptedAt))->toBeTrue();
});

it('validates the name', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->patchJson('/api/v1/me', ['name' => ''])
        ->assertUnprocessable()
        ->assertJsonPath('errors.name.0', 'ad soyad alanı zorunludur.');
});

it('logs out by deleting only the current token', function () {
    $user = User::factory()->create();
    $current = $user->createToken('phone')->plainTextToken;
    $user->createToken('tablet');

    $this->withToken($current)->postJson('/api/v1/auth/logout')->assertNoContent();

    expect($user->tokens()->pluck('name')->all())->toBe(['tablet']);

    $this->app['auth']->forgetGuards();
    $this->withToken($current)->getJson('/api/v1/me')->assertUnauthorized();
});

it('registers a device and upserts by FCM token', function () {
    $first = User::factory()->create();
    $second = User::factory()->create();

    Sanctum::actingAs($first);
    $this->postJson('/api/v1/devices', ['platform' => 'android', 'fcm_token' => 'token-1'])->assertNoContent();
    $this->postJson('/api/v1/devices', ['platform' => 'android', 'fcm_token' => 'token-1'])->assertNoContent();

    expect(Device::count())->toBe(1);

    // Aynı cihazda başka hesapla giriş: token yeni kullanıcıya geçer.
    Sanctum::actingAs($second);
    $this->postJson('/api/v1/devices', ['platform' => 'android', 'fcm_token' => 'token-1'])->assertNoContent();

    expect(Device::sole()->user_id)->toBe($second->id);
});

it('validates device payload', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/v1/devices', ['platform' => 'windows'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['platform', 'fcm_token']);
});

it('answers unknown API routes with a Turkish 404 message', function () {
    $this->getJson('/api/v1/yok')
        ->assertNotFound()
        ->assertExactJson(['message' => 'Kayıt bulunamadı.']);
});
