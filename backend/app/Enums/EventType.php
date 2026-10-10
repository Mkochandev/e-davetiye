<?php

namespace App\Enums;

enum EventType: string
{
    case Wedding = 'wedding';
    case Engagement = 'engagement';
    case Henna = 'henna';
    case Circumcision = 'circumcision';
    case Birthday = 'birthday';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Wedding => 'Düğün',
            self::Engagement => 'Nişan',
            self::Henna => 'Kına gecesi',
            self::Circumcision => 'Sünnet düğünü',
            self::Birthday => 'Doğum günü',
            self::Other => 'Diğer',
        };
    }
}
