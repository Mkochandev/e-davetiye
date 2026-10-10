<?php

namespace App\Services\Purchases;

use Illuminate\Http\JsonResponse;
use RuntimeException;

class PackAlreadyOwned extends RuntimeException
{
    public function render(): JsonResponse
    {
        return response()->json([
            'code' => 'pack_exists',
            'message' => 'Bu etkinlik için Etkinlik Paketi zaten alınmış.',
        ], 409);
    }
}
