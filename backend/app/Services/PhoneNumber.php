<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;
use libphonenumber\NumberParseException;
use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberUtil;

/**
 * Telefon numaralarının tek normalizasyon noktası. Her yerde E.164 (`+905321112233`) saklanır.
 * Faz 1 yalnızca Türkiye numaralarını kabul eder (spec §2).
 */
class PhoneNumber
{
    public const DEFAULT_REGION = 'TR';

    /** @var list<string> */
    public const ALLOWED_REGIONS = ['TR'];

    public const INVALID_MESSAGE = 'Geçerli bir telefon numarası girin.';

    public function __construct(private readonly PhoneNumberUtil $util) {}

    /**
     * E.164'e çevirir; geçersizse doğrulama hatası fırlatır.
     *
     * @throws ValidationException
     */
    public function normalize(?string $input, string $attribute = 'phone'): string
    {
        return $this->tryNormalize($input)
            ?? throw ValidationException::withMessages([$attribute => self::INVALID_MESSAGE]);
    }

    public function tryNormalize(?string $input): ?string
    {
        $input = trim((string) $input);

        if ($input === '') {
            return null;
        }

        try {
            $number = $this->util->parse($input, self::DEFAULT_REGION);
        } catch (NumberParseException) {
            return null;
        }

        if (! $this->util->isValidNumber($number)
            || ! in_array($this->util->getRegionCodeForNumber($number), self::ALLOWED_REGIONS, true)) {
            return null;
        }

        return $this->util->format($number, PhoneNumberFormat::E164);
    }

    /**
     * E.164 numaranın SHA-256 hex özeti. Mobil rehber eşleştirmesi de aynı girdiyi (E.164 metni) hash'ler.
     */
    public function hash(string $e164): string
    {
        return hash('sha256', $e164);
    }

    /**
     * Log için maskeler: `+905321112233` → `+90532***2233`.
     */
    public static function mask(?string $phone): string
    {
        $phone = (string) $phone;

        if (strlen($phone) <= 10) {
            return str_repeat('*', strlen($phone));
        }

        return substr($phone, 0, 6).'***'.substr($phone, -4);
    }
}
