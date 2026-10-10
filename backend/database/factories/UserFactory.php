<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    public function definition(): array
    {
        $phone = fake()->unique()->numerify('+90532#######');

        return [
            'phone' => $phone,
            'phone_hash' => hash('sha256', $phone),
            'name' => fake()->name(),
            'kvkk_accepted_at' => now(),
            'kvkk_version' => config('zarf.kvkk_version'),
        ];
    }

    /**
     * Profilini henüz tamamlamamış kullanıcı.
     */
    public function incomplete(): static
    {
        return $this->state(fn () => [
            'name' => null,
            'kvkk_accepted_at' => null,
            'kvkk_version' => null,
        ]);
    }
}
