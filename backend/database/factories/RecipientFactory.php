<?php

namespace Database\Factories;

use App\Enums\RecipientChannel;
use App\Models\Invitation;
use App\Models\Recipient;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Recipient>
 */
class RecipientFactory extends Factory
{
    public function definition(): array
    {
        $phone = fake()->unique()->numerify('+90535#######');

        return [
            'invitation_id' => Invitation::factory(),
            'phone' => $phone,
            'phone_hash' => fn (array $attributes) => hash('sha256', $attributes['phone']),
            'name' => fake()->name(),
            'token' => Str::random(40),
        ];
    }

    public function sentByLink(): static
    {
        return $this->state(fn () => [
            'channel' => RecipientChannel::Link,
            'sent_at' => now(),
        ]);
    }
}
