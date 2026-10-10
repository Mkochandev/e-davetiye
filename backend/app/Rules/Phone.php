<?php

namespace App\Rules;

use App\Services\PhoneNumber;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Girdinin PhoneNumber servisiyle E.164'e çevrilebildiğini doğrular. Normalizasyonu yapmaz.
 */
class Phone implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || app(PhoneNumber::class)->tryNormalize($value) === null) {
            $fail(PhoneNumber::INVALID_MESSAGE);
        }
    }
}
