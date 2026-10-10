<?php

namespace App\Services;

use App\Enums\EventCreator;
use App\Enums\EventType;
use App\Enums\ReservationStatus;
use App\Events\SalonEventLinked;
use App\Models\Event;
use App\Models\Reservation;
use App\Models\Salon;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Salon rezervasyonu → etkinlik (spec §3.1).
 */
class ReservationService
{
    public const DISPLAY_TIMEZONE = 'Europe/Istanbul';

    public function __construct(private readonly PhoneNumber $phones) {}

    /**
     * @param array{
     *     contact_name: string,
     *     couple_phone: string,
     *     type: EventType|string,
     *     title: string,
     *     date: string,
     *     start_time: string,
     *     end_time: string,
     *     status: ReservationStatus|string,
     *     total_amount: int,
     *     deposit_amount: int,
     *     paid_amount: int,
     *     notes?: string|null,
     * } $data  Tarih/saat İstanbul saatiyle, tutarlar kuruş.
     */
    public function save(Salon $salon, array $data, ?Reservation $reservation = null): Reservation
    {
        $couplePhone = $this->phones->normalize($data['couple_phone'], 'couple_phone');
        [$startsAt, $endsAt] = self::period($data['date'], $data['start_time'], $data['end_time']);
        $status = ReservationStatus::from($data['status'] instanceof ReservationStatus ? $data['status']->value : $data['status']);

        $linked = DB::transaction(function () use ($salon, $data, $reservation, $couplePhone, $startsAt, $endsAt, $status, &$saved) {
            $event = $reservation?->event ?? new Event([
                'salon_id' => $salon->id,
                'created_by' => EventCreator::Salon,
            ]);

            $phoneChanged = $event->couple_phone !== $couplePhone;

            $event->fill([
                'type' => $data['type'],
                'title' => $data['title'],
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'venue_name' => $salon->name,
                'venue_address' => collect([$salon->address, $salon->district, $salon->city])->filter()->implode(', ') ?: null,
                'latitude' => $salon->latitude,
                'longitude' => $salon->longitude,
                'couple_phone' => $couplePhone,
                'cancelled_at' => $status === ReservationStatus::Cancelled
                    ? ($event->cancelled_at ?? now())
                    : null,
            ]);

            $newlyLinked = false;

            if ($phoneChanged || $event->owner_user_id === null) {
                $ownerId = User::where('phone', $couplePhone)->value('id');
                $newlyLinked = $ownerId !== null && $ownerId !== $event->owner_user_id;
                $event->owner_user_id = $ownerId;
            }

            $event->save();

            $saved = $reservation ?? new Reservation(['salon_id' => $salon->id]);
            $saved->fill([
                'event_id' => $event->id,
                'contact_name' => $data['contact_name'],
                'contact_phone' => $couplePhone,
                'status' => $status,
                'total_amount' => $data['total_amount'],
                'deposit_amount' => $data['deposit_amount'],
                'paid_amount' => $data['paid_amount'],
                'notes' => $data['notes'] ?? null,
            ]);
            $saved->save();

            return $newlyLinked ? $event : null;
        });

        if ($linked) {
            SalonEventLinked::dispatch($linked);
        }

        return $saved->refresh();
    }

    /**
     * Aynı salonda saatleri çakışan kesin rezervasyonlar.
     *
     * @return Collection<int, Reservation>
     */
    public function overlapping(Salon $salon, CarbonImmutable $startsAt, CarbonImmutable $endsAt, ?Reservation $except = null): Collection
    {
        return Reservation::query()
            ->with('event')
            ->where('salon_id', $salon->id)
            ->where('status', ReservationStatus::Confirmed)
            ->when($except, fn ($q) => $q->whereKeyNot($except->getKey()))
            ->whereHas('event', fn ($q) => $q
                ->where('starts_at', '<', $endsAt)
                ->where('ends_at', '>', $startsAt))
            ->get();
    }

    /**
     * İstanbul saatiyle girilen gün ve saat aralığını UTC'ye çevirir. Bitiş başlangıçtan önceyse ertesi gündür.
     *
     * @return array{0: CarbonImmutable, 1: CarbonImmutable}
     */
    public static function period(string $date, string $startTime, string $endTime): array
    {
        $day = CarbonImmutable::parse($date, self::DISPLAY_TIMEZONE)->startOfDay();
        $start = $day->setTimeFromTimeString($startTime);
        $end = $day->setTimeFromTimeString($endTime);

        if ($end->lessThanOrEqualTo($start)) {
            $end = $end->addDay();
        }

        return [$start->utc(), $end->utc()];
    }
}
