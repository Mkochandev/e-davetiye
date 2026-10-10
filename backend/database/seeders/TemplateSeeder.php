<?php

namespace Database\Seeders;

use App\Enums\EventType;
use App\Models\Template;
use Illuminate\Database\Seeder;

/**
 * Spec §11'deki 6 şablon. Slug'a göre güncellenir, tekrar çalıştırmak güvenli.
 */
class TemplateSeeder extends Seeder
{
    public function run(): void
    {
        $eventTypes = array_column(EventType::cases(), 'value');

        foreach (self::definitions() as $order => $template) {
            Template::updateOrCreate(
                ['slug' => $template['slug']],
                [
                    'name' => $template['name'],
                    'event_types' => $eventTypes,
                    'is_premium' => $template['is_premium'],
                    'config' => $template['config'],
                    'sort_order' => $order + 1,
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * @return list<array{slug: string, name: string, is_premium: bool, config: array<string, mixed>}>
     */
    public static function definitions(): array
    {
        $config = fn (array $overrides) => array_merge([
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
        ], $overrides);

        return [
            [
                'slug' => 'klasik',
                'name' => 'Klasik',
                'is_premium' => false,
                'config' => $config([]),
            ],
            [
                'slug' => 'modern',
                'name' => 'Modern',
                'is_premium' => false,
                'config' => $config([
                    'accent' => '#17182A',
                    'frame' => 'none',
                    'frame_color' => null,
                    'display_font' => 'Plus Jakarta Sans',
                    'ornament' => 'top-band',
                ]),
            ],
            [
                'slug' => 'bahar',
                'name' => 'Bahar',
                'is_premium' => false,
                'config' => $config([
                    'paper' => '#F3F6F1',
                    'muted' => '#56614F',
                    'accent' => '#5E7C55',
                    'frame' => 'single',
                    'frame_color' => '#D5DFD0',
                    'ornament' => 'top-band',
                ]),
            ],
            [
                'slug' => 'minimal',
                'name' => 'Minimal',
                'is_premium' => false,
                'config' => $config([
                    'paper' => '#FAFAFA',
                    'accent' => '#5F6273',
                    'frame' => 'single',
                    'frame_color' => '#E3E4EA',
                    'display_font' => 'Plus Jakarta Sans',
                    'ornament' => 'none',
                ]),
            ],
            [
                'slug' => 'altin-varak',
                'name' => 'Altın Varak',
                'is_premium' => true,
                'config' => $config([
                    'accent' => '#A8833A',
                    'frame_color' => '#E6D9BC',
                ]),
            ],
            [
                'slug' => 'gece-mavisi',
                'name' => 'Gece Mavisi',
                'is_premium' => true,
                'config' => $config([
                    'paper' => '#1D2340',
                    'ink' => '#FFFFFF',
                    'muted' => '#C9CBDA',
                    'accent' => '#D9B46A',
                    'frame' => 'single',
                    'frame_color' => '#3A4266',
                ]),
            ],
        ];
    }
}
