<?php

namespace Database\Factories;

use App\Enums\SalonUserRole;
use App\Models\Salon;
use App\Models\SalonUser;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SalonUser>
 */
class SalonUserFactory extends Factory
{
    public function definition(): array
    {
        return [
            'salon_id' => Salon::factory(),
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->unique()->numerify('+90533#######'),
            'password' => 'password',
            'role' => SalonUserRole::Owner,
        ];
    }

    public function staff(): static
    {
        return $this->state(fn () => ['role' => SalonUserRole::Staff]);
    }
}
