<?php

namespace Database\Factories;

use App\Enums\ReservationStatus;
use App\Models\Event;
use App\Models\Reservation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Reservation>
 */
class ReservationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory()->fromSalon(),
            'salon_id' => fn (array $attributes) => Event::find($attributes['event_id'])->salon_id,
            'contact_name' => fake()->name(),
            'contact_phone' => fn (array $attributes) => Event::find($attributes['event_id'])->couple_phone,
            'status' => ReservationStatus::Confirmed,
            'total_amount' => 15_000_000,
            'deposit_amount' => 3_000_000,
            'paid_amount' => 3_000_000,
        ];
    }
}
