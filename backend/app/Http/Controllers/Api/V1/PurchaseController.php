<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\Platform;
use App\Http\Controllers\Controller;
use App\Http\Resources\EventResource;
use App\Services\Purchases\PurchaseService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PurchaseController extends Controller
{
    /**
     * Etkinlik Paketi (spec §6.5). Doğrulanırsa `pack_purchased_at` dolar; aynı işlem tekrar gelirse aynı yanıt;
     * paketi olan etkinliğe yeni işlem 409.
     */
    public function store(Request $request, PurchaseService $purchases): JsonResponse
    {
        $data = $request->validate([
            'platform' => ['required', Rule::enum(Platform::class)],
            'product_id' => ['required', 'string', Rule::in([config('zarf.product_id')])],
            'event_id' => ['required', 'integer'],
            'verification_data' => ['required', 'string', 'max:20000'],
        ], [], [
            'platform' => 'platform',
            'product_id' => 'ürün',
            'event_id' => 'etkinlik',
            'verification_data' => 'doğrulama verisi',
        ]);

        // Başkasının etkinliği 404 (varlığı belli etme).
        $event = $request->user()->events()->whereKey($data['event_id'])->firstOrFail();

        $purchase = $purchases->purchase(
            $request->user(),
            $event,
            Platform::from($data['platform']),
            $data['product_id'],
            $data['verification_data'],
        );

        return response()->json([
            'transaction_id' => $purchase->transaction_id,
            'event' => new EventResource($purchase->event()->with(['salon', 'invitation'])->first()),
        ]);
    }
}
