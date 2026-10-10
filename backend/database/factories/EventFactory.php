<?php

namespace Database\Factories;

use App\Enums\EventCreator;
use App\Enums\EventType;
use App\Models\Event;
use App\Models\Salon;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    public function definition(): array
    {
        $startsAt = now()->addMonths(2)->setTime(16, 0);

        return [
            'owner_user_id' => User::factory(),
            'salon_id' => null,
            'created_by' => EventCreator::User,
            'type' => EventType::Wedding,
            'title' => fake()->firstNameFemale().' & '.fake()->firstNameMale(),
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->copy()->addHours(4),
            'venue_name' => fake()->company().' Düğün Salonu',
            'venue_address' => fake()->streetAddress().', Gaziantep',
            'latitude' => 37.0662,
            'longitude' => 37.3833,
        ];
    }

    /**
     * Salonun rezervasyondan açtığı, henüz çifte bağlanmamış etkinlik.
     */
    public function fromSalon(?string $couplePhone = null): static
    {
        return $this->state(fn () => [
            'owner_user_id' => null,
            'salon_id' => Salon::factory(),
            'created_by' => EventCreator::Salon,
            'couple_phone' => $couplePhone ?? fake()->unique()->numerify('+90532#######'),
        ]);
    }
}
