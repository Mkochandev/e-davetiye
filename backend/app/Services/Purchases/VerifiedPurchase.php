<?php

namespace App\Services\Purchases;

final class VerifiedPurchase
{
    /**
     * @param  array<string, mixed>  $payload  Mağazanın yanıtı (kayıt için).
     */
    public function __construct(
        public readonly string $transactionId,
        public readonly string $productId,
        public readonly array $payload = [],
    ) {}
}
