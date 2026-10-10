<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Invitation;
use App\Models\Template;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invitation>
 */
class InvitationFactory extends Factory
{
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'template_id' => Template::factory(),
            'texts' => [
                'label' => 'DÜĞÜN DAVETİ',
                'intro' => '',
                'names' => 'Elif & Emre',
                'message' => 'düğünümüzde sizi de aramızda görmekten mutluluk duyarız.',
            ],
            'envelope_color' => 'sage',
            'seal_color' => 'bordo',
            'seal_text' => 'E&E',
        ];
    }
}
