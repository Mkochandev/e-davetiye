<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(fn () => Sanctum::actingAs(User::factory()->create()));

it('returns only the hashes of registered users', function () {
    User::factory()->create(['phone' => '+905361112233', 'phone_hash' => hash('sha256', '+905361112233')]);

    $registered = hash('sha256', '+905361112233');
    $unknown = hash('sha256', '+905369998877');

    $this->postJson('/api/v1/contacts/match', ['hashes' => [strtoupper($registered), $unknown, $registered]])
        ->assertOk()
        ->assertExactJson(['registered' => [$registered]]);
});

it('does not store the submitted hashes', function () {
    $tables = collect(DB::select("select table_name from information_schema.tables where table_schema = 'public'"))
        ->pluck('table_name');
    $before = $tables->mapWithKeys(fn ($t) => [$t => DB::table($t)->count()]);

    $this->postJson('/api/v1/contacts/match', ['hashes' => [hash('sha256', '+905369998877')]])->assertOk();

    // Yalnız oturum/token yan etkileri olabilir; hash'in yazıldığı bir tablo olmamalı.
    $after = $tables->mapWithKeys(fn ($t) => [$t => DB::table($t)->count()]);
    expect($after->except(['personal_access_tokens', 'cache', 'cache_locks', 'sessions'])->all())
        ->toBe($before->except(['personal_access_tokens', 'cache', 'cache_locks', 'sessions'])->all());
});

it('accepts at most 2000 well-formed hashes', function () {
    $this->postJson('/api/v1/contacts/match', ['hashes' => array_fill(0, 2001, hash('sha256', 'x'))])
        ->assertUnprocessable()
        ->assertJsonPath('errors.hashes.0', 'En fazla 2.000 numara eşleştirilebilir.');

    $this->postJson('/api/v1/contacts/match', ['hashes' => ['+905361112233']])
        ->assertJsonValidationErrors('hashes.0');

    $this->postJson('/api/v1/contacts/match', ['hashes' => array_fill(0, 2000, hash('sha256', 'x'))])->assertOk();
});
