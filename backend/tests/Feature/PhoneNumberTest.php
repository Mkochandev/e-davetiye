<?php

use App\Services\PhoneNumber;
use Illuminate\Validation\ValidationException;

beforeEach(function () {
    $this->phones = app(PhoneNumber::class);
});

it('normalizes Turkish numbers to E.164', function (string $input) {
    expect($this->phones->normalize($input))->toBe('+905321112233');
})->with([
    '5321112233',
    '05321112233',
    '0532 111 22 33',
    '(0532) 111-22-33',
    '+90 532 111 22 33',
    '905321112233',
    '+905321112233',
]);

it('rejects invalid or non-Turkish numbers', function (?string $input) {
    expect($this->phones->tryNormalize($input))->toBeNull()
        ->and(fn () => $this->phones->normalize($input))->toThrow(ValidationException::class);
})->with([
    '',
    null,
    'abc',
    '12345',
    '532111223',
    '+14155552671',
    '+447911123456',
]);

it('hashes the E.164 form with SHA-256', function () {
    expect($this->phones->hash('+905321112233'))
        ->toBe(hash('sha256', '+905321112233'))
        ->toHaveLength(64);
});

it('masks numbers for logs', function () {
    expect(PhoneNumber::mask('+905321112233'))->toBe('+90532***2233');
});
