<?php

namespace App\Services\Purchases;

/**
 * Mağazadan gelen satın almayı sunucu tarafında doğrular (spec §12).
 */
interface PurchaseVerifier
{
    /**
     * @param  string  $verificationData  iOS: StoreKit 2 işlem JWS'i; Android: satın alma token'ı.
     *
     * @throws PurchaseRejected Mağaza işlemi geçersiz/iptal/başka ürün diyor.
     * @throws StoreUnavailable Mağazaya ulaşılamadı ya da kimlik bilgileri eksik; sonra tekrar denenmeli.
     */
    public function verify(string $productId, string $verificationData): VerifiedPurchase;
}
