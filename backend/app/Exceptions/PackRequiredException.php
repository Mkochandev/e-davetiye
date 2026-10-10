<?php

namespace App\Exceptions;

use Exception;
use Illuminate\Http\JsonResponse;

/**
 * Paketsiz etkinlikte premium şablon/renk ya da ücretsiz sınırı aşan davetli (spec §4).
 */
class PackRequiredException extends Exception
{
    public function __construct(string $message = 'Bu özellik için Etkinlik Paketi gerekiyor.')
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'code' => 'pack_required',
            'message' => $this->getMessage(),
        ], 402);
    }
}
