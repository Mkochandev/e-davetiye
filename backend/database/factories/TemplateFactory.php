<?php

namespace Database\Factories;

use App\Enums\EventType;
use App\Models\Template;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Template>
 */
class TemplateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->unique()->word(),
            'slug' => fake()->unique()->slug(2),
            'event_types' => array_column(EventType::cases(), 'value'),
            'is_premium' => false,
            'config' => [
                'v' => 1,
                'paper' => '#FFFFFF',
                'ink' => '#17182A',
                'muted' => '#5F6273',
                'accent' => '#8A1F3D',
                'frame' => 'double',
                'frame_color' => '#ECE4E8',
                'display_font' => 'Fraunces',
                'body_font' => 'Plus Jakarta Sans',
                'ornament' => 'dot-rule',
            ],
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function premium(): static
    {
        return $this->state(fn () => ['is_premium' => true]);
    }
}
