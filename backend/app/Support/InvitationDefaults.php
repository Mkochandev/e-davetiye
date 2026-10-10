<?php

namespace App\Support;

use App\Enums\EventType;
use App\Models\Event;

/**
 * Yeni davetiyede etkinlik türüne göre varsayılanlar (spec §11).
 */
class InvitationDefaults
{
    public const ENVELOPE_COLOR = 'sage';

    public const SEAL_COLOR = 'bordo';

    /**
     * @return array{label: string, intro: string, names: string, message: string}
     */
    public static function texts(Event $event): array
    {
        [$label, $message] = match ($event->type) {
            EventType::Wedding => ['DÜĞÜN DAVETİ', 'düğünümüzde sizi de aramızda görmekten mutluluk duyarız.'],
            EventType::Engagement => ['NİŞAN DAVETİ', 'nişanımızda sizi de aramızda görmekten mutluluk duyarız.'],
            EventType::Henna => ['KINA GECESİ DAVETİ', 'kına gecemizde sizi de aramızda görmekten mutluluk duyarız.'],
            EventType::Circumcision => ['SÜNNET DÜĞÜNÜ DAVETİ', 'oğlumuzun sünnet düğününde sizi de aramızda görmekten mutluluk duyarız.'],
            EventType::Birthday => ['DOĞUM GÜNÜ DAVETİ', 'doğum günümüzde sizi de aramızda görmek isteriz.'],
            EventType::Other => ['DAVET', 'sizi de aramızda görmekten mutluluk duyarız.'],
        };

        return [
            'label' => $label,
            'intro' => '',
            'names' => $event->title,
            'message' => $message,
        ];
    }
}
