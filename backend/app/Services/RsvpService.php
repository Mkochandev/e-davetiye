<?php

namespace App\Services;

use App\Enums\RsvpStatus;
use App\Events\RsvpReceived;
use App\Models\Recipient;
use Illuminate\Validation\ValidationException;

/**
 * Zarf açma ve LCV; uygulama ve web aynı kuralları kullanır (spec §3.5, §3.6, §6.2).
 */
class RsvpService
{
    public const MAX_GUESTS = 10;

    /**
     * `opened_at` yalnız ilk seferde dolar.
     */
    public function markOpened(Recipient $recipient): void
    {
        if ($recipient->opened_at === null) {
            $recipient->forceFill(['opened_at' => now()])->save();
        }
    }

    /**
     * @throws ValidationException İptal edilmiş etkinlik ya da geçmiş son tarih.
     */
    public function respond(Recipient $recipient, RsvpStatus $status, ?int $guestCount): void
    {
        $invitation = $recipient->invitation;

        if ($invitation->event->isCancelled()) {
            throw ValidationException::withMessages(['status' => 'Bu etkinlik iptal edildi.']);
        }

        if ($invitation->rsvpClosed()) {
            throw ValidationException::withMessages(['status' => 'LCV son tarihi geçti, yanıt değiştirilemez.']);
        }

        $recipient->forceFill([
            'rsvp_status' => $status,
            'guest_count' => $status === RsvpStatus::Attending ? $guestCount : null,
            'responded_at' => now(),
            // Zarfı açmadan yanıt verilemez; yine de istatistik tutarlı kalsın.
            'opened_at' => $recipient->opened_at ?? now(),
        ])->save();

        RsvpReceived::dispatch($recipient);
    }

    /**
     * @return array<string, mixed>
     */
    public static function rules(): array
    {
        return [
            'status' => ['required', 'string', 'in:'.RsvpStatus::Attending->value.','.RsvpStatus::NotAttending->value],
            'guest_count' => ['exclude_unless:status,'.RsvpStatus::Attending->value, 'required', 'integer', 'between:1,'.self::MAX_GUESTS],
        ];
    }

    /**
     * @return array<string, string>
     */
    public static function attributes(): array
    {
        return ['status' => 'katılım durumu', 'guest_count' => 'kişi sayısı'];
    }
}
