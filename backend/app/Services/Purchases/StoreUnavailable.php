<?php

namespace App\Services\Purchases;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Mağaza API'sine ulaşılamadı ya da sunucu yapılandırması eksik. Satın alma onaylanmaz, sessizce
 * kabul de edilmez; istemci satın almayı tamamlamadan bekletir ve sonra tekrar gönderir.
 */
class StoreUnavailable extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json([
            'code' => 'store_unavailable',
            'message' => 'Satın alma şu anda doğrulanamıyor. Lütfen biraz sonra tekrar deneyin.',
        ], 503);
    }
}
