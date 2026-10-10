<?php

namespace App\Services\Purchases;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Mağaza satın almayı onaylamadı. İşlem kimliği biliniyorsa `failed` olarak kaydedilir.
 */
class PurchaseRejected extends Exception
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        string $reason,
        public readonly ?string $transactionId = null,
        public readonly array $payload = [],
    ) {
        parent::__construct($reason);
    }

    /**
     * Ayrıntı (mağazanın gerekçesi) loglanır; kullanıcıya genel mesaj gider.
     */
    public function render(): JsonResponse
    {
        return response()->json([
            'code' => 'verification_failed',
            'message' => 'Satın alma doğrulanamadı.',
        ], 422);
    }
}
