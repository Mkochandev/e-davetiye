<?php

namespace App\Services\Purchases;

use Illuminate\Support\Str;

/**
 * Yalnız local/testing (spec §12). `fake-ok` kabul eder; `fake-ok:abc` aynı işlem kimliğini (`fake-abc`)
 * üretir ki tekrar gönderim denenebilsin. Başka her şey reddedilir.
 */
class FakePurchaseVerifier implements PurchaseVerifier
{
    public function verify(string $productId, string $verificationData): VerifiedPurchase
    {
        if ($verificationData === 'fake-ok') {
            return new VerifiedPurchase('fake-'.Str::random(16), $productId, ['fake' => true]);
        }

        if (str_starts_with($verificationData, 'fake-ok:')) {
            return new VerifiedPurchase('fake-'.substr($verificationData, 8), $productId, ['fake' => true]);
        }

        if (str_starts_with($verificationData, 'fake-fail:')) {
            throw new PurchaseRejected('Sahte doğrulama reddedildi.', 'fake-'.substr($verificationData, 10));
        }

        throw new PurchaseRejected('Sahte doğrulama reddedildi.');
    }
}
