<?php

namespace Database\Factories;

use App\Models\Salon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Salon>
 */
class SalonFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->company().' Düğün Salonu',
            'phone' => fake()->numerify('+90342#######'),
            'email' => fake()->unique()->companyEmail(),
            'address' => fake()->streetAddress(),
            'city' => 'Gaziantep',
            'district' => 'Şehitkamil',
            'latitude' => 37.0662,
            'longitude' => 37.3833,
            'capacity' => 500,
            'is_active' => true,
        ];
    }
}
