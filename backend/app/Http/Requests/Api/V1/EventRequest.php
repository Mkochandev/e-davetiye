<?php

namespace App\Http\Requests\Api\V1;

use App\Enums\EventType;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /events (tüm alanlar) ve PATCH /events/{event} (gönderilen alanlar).
 * Saat dilimi verilmemiş zamanlar İstanbul saati kabul edilir; UTC saklanır.
 */
class EventRequest extends FormRequest
{
    public function rules(): array
    {
        $required = $this->isMethod('post') ? 'required' : 'sometimes';

        // PATCH'te başlangıç gönderilmediyse bitiş mevcut başlangıçla karşılaştırılır.
        $endsAfter = $this->has('starts_at') || ! $this->route('event')
            ? 'after:starts_at'
            : 'after:'.$this->route('event')->starts_at->toIso8601String();

        return [
            'type' => [$required, Rule::enum(EventType::class)],
            'title' => [$required, 'string', 'min:2', 'max:120'],
            'starts_at' => [$required, 'date'],
            'ends_at' => ['nullable', 'date', $endsAfter],
            'venue_name' => [$required, 'string', 'max:255'],
            'venue_address' => ['nullable', 'string', 'max:255'],
            'latitude' => ['nullable', 'numeric', 'between:-90,90', 'required_with:longitude'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180', 'required_with:latitude'],
        ];
    }

    public function attributes(): array
    {
        return [
            'type' => 'etkinlik türü',
            'title' => 'başlık',
            'starts_at' => 'başlangıç zamanı',
            'ends_at' => 'bitiş zamanı',
            'venue_name' => 'mekan adı',
            'venue_address' => 'mekan adresi',
            'latitude' => 'enlem',
            'longitude' => 'boylam',
        ];
    }

    /**
     * Doğrulanmış alanlar; zamanlar UTC'ye çevrilmiş.
     *
     * @return array<string, mixed>
     */
    public function eventData(): array
    {
        $data = $this->validated();

        foreach (['starts_at', 'ends_at'] as $key) {
            if (isset($data[$key])) {
                $data[$key] = self::toUtc($data[$key]);
            }
        }

        return $data;
    }

    public static function toUtc(string $value): CarbonImmutable
    {
        return CarbonImmutable::parse($value, 'Europe/Istanbul')->utc();
    }
}
