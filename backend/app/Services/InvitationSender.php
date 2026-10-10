<?php

namespace App\Services;

use App\Enums\RecipientChannel;
use App\Events\EnvelopeDelivered;
use App\Models\Invitation;
use App\Models\Recipient;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * "Gönder" (spec §3.4): henüz gönderilmemiş tüm davetlileri gönderir. Kayıtlı kullanıcı posta kutusundan,
 * kayıtsız kişi kendine özel web linkinden alır. Tekrar çağrılınca yalnız yeni eklenenler gönderilir.
 */
class InvitationSender
{
    /**
     * @return array{in_app: Collection<int, Recipient>, link: Collection<int, Recipient>}
     *
     * @throws ValidationException
     */
    public function send(Invitation $invitation): array
    {
        if ($invitation->event->isCancelled()) {
            throw ValidationException::withMessages(['event' => 'İptal edilmiş bir etkinliğin davetiyesi gönderilemez.']);
        }

        $sent = DB::transaction(function () use ($invitation) {
            // Aynı anda iki "Gönder" aynı davetliyi iki kez göndermesin.
            Invitation::whereKey($invitation->id)->lockForUpdate()->first();

            $pending = $invitation->recipients()->whereNull('sent_at')->orderBy('id')->get();
            $users = User::whereIn('phone_hash', $pending->pluck('phone_hash'))->pluck('id', 'phone_hash');
            $now = now();

            foreach ($pending as $recipient) {
                $userId = $recipient->user_id ?? $users[$recipient->phone_hash] ?? null;

                $recipient->forceFill([
                    'user_id' => $userId,
                    'channel' => $userId ? RecipientChannel::InApp : RecipientChannel::Link,
                    'sent_at' => $now,
                ])->save();
            }

            if ($pending->isNotEmpty() && $invitation->first_sent_at === null) {
                $invitation->forceFill(['first_sent_at' => $now])->save();
            }

            return $pending;
        });

        $sent->each->setRelation('invitation', $invitation);
        [$inApp, $link] = $sent->partition(fn (Recipient $r) => $r->channel === RecipientChannel::InApp);

        $inApp->each(fn (Recipient $r) => EnvelopeDelivered::dispatch($r));

        return ['in_app' => $inApp->values(), 'link' => $link->values()];
    }
}
