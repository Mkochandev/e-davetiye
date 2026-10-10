<?php

namespace App\Services\Purchases;

use App\Enums\Platform;
use Illuminate\Contracts\Foundation\Application;

/**
 * Platforma göre doğrulayıcı. local/testing'de yalnız sahte doğrulayıcı; başka hiçbir ortamda açılamaz.
 */
class PurchaseVerifiers
{
    public function __construct(private readonly Application $app) {}

    public function for(Platform $platform): PurchaseVerifier
    {
        if ($this->app->environment(['local', 'testing'])) {
            return new FakePurchaseVerifier;
        }

        return match ($platform) {
            Platform::Ios => new AppleVerifier([
                'key_id' => config('services.apple_iap.key_id'),
                'issuer_id' => config('services.apple_iap.issuer_id'),
                'bundle_id' => config('services.apple_iap.bundle_id'),
                'private_key' => self::readFile(config('services.apple_iap.private_key_path')),
            ]),
            Platform::Android => new GooglePlayVerifier([
                'package_name' => config('services.google_play.package_name'),
                'service_account' => json_decode((string) self::readFile(config('services.google_play.service_account_path')), true),
            ]),
        };
    }

    private static function readFile(?string $path): ?string
    {
        return $path && is_readable($path) ? file_get_contents($path) : null;
    }
}
