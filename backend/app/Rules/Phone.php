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
    /**
     * @param  bool  $mobileOnly  Yalnız cep numarası (SMS ile giriş kodu için).
     */
    public function __construct(private readonly bool $mobileOnly = false) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $phones = app(PhoneNumber::class);
        $e164 = is_string($value) ? $phones->tryNormalize($value) : null;

        if ($e164 === null) {
            $fail(PhoneNumber::INVALID_MESSAGE);
        } elseif ($this->mobileOnly && ! $phones->isMobile($e164)) {
            $fail(PhoneNumber::NOT_MOBILE_MESSAGE);
        }
    }
}
