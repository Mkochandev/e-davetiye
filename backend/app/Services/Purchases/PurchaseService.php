<?php

namespace App\Services\Purchases;

use App\Enums\Platform;
use App\Enums\PurchaseStatus;
use App\Models\Event;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Etkinlik Paketi satın alması (spec §3.3, §6.5, §12): doğrula → kaydet → `events.pack_purchased_at`.
 * Aynı `transaction_id` ikinci kez gelirse yeni paket açılmaz, aynı başarılı sonuç döner.
 */
class PurchaseService
{
    public function __construct(private readonly PurchaseVerifiers $verifiers) {}

    /**
     * @throws PurchaseRejected
     * @throws PackAlreadyOwned
     * @throws StoreUnavailable
     */
    public function purchase(User $user, Event $event, Platform $platform, string $productId, string $verificationData): Purchase
    {
        try {
            $verified = $this->verifiers->for($platform)->verify($productId, $verificationData);
        } catch (PurchaseRejected $e) {
            $this->recordFailure($user, $event, $platform, $productId, $e);

            throw $e;
        }

        return DB::transaction(function () use ($user, $event, $platform, $productId, $verified) {
            // Aynı etkinliğe eşzamanlı iki satın alma tek paket açsın.
            $event = Event::whereKey($event->id)->lockForUpdate()->firstOrFail();

            $existing = Purchase::where('transaction_id', $verified->transactionId)->lockForUpdate()->first();
            if ($existing?->status === PurchaseStatus::Verified) {
                return $existing;
            }

            if ($event->hasPack()) {
                throw new PackAlreadyOwned;
            }

            $purchase = $existing ?? new Purchase(['transaction_id' => $verified->transactionId]);
            $purchase->fill([
                'user_id' => $user->id,
                'event_id' => $event->id,
                'platform' => $platform,
                'product_id' => $productId,
                'status' => PurchaseStatus::Verified,
                'payload' => $verified->payload,
                'verified_at' => now(),
            ])->save();

            $event->forceFill(['pack_purchased_at' => now()])->save();

            return $purchase;
        });
    }

    private function recordFailure(User $user, Event $event, Platform $platform, string $productId, PurchaseRejected $e): void
    {
        Log::warning('Satın alma doğrulanamadı', [
            'user_id' => $user->id,
            'event_id' => $event->id,
            'platform' => $platform->value,
            'reason' => $e->getMessage(),
        ]);

        if ($e->transactionId === null) {
            return;
        }

        // Doğrulanmış bir kayıt asla `failed` ile ezilmez.
        Purchase::firstOrCreate(
            ['transaction_id' => $e->transactionId],
            [
                'user_id' => $user->id,
                'event_id' => $event->id,
                'platform' => $platform,
                'product_id' => $productId,
                'status' => PurchaseStatus::Failed,
                'payload' => $e->payload + ['reason' => $e->getMessage()],
            ],
        );
    }
}
