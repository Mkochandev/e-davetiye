<?php

use App\Enums\SalonUserRole;
use App\Models\Admin;
use App\Models\SalonUser;
use App\Models\Template;

it('seeds an admin, a demo salon owner and the six templates', function () {
    $this->seed();

    expect(Admin::count())->toBe(1)
        ->and(SalonUser::sole()->role)->toBe(SalonUserRole::Owner)
        ->and(Template::orderBy('sort_order')->pluck('slug')->all())
        ->toBe(['klasik', 'modern', 'bahar', 'minimal', 'altin-varak', 'gece-mavisi'])
        ->and(Template::where('is_premium', true)->pluck('slug')->all())
        ->toEqualCanonicalizing(['altin-varak', 'gece-mavisi']);

    $geceMavisi = Template::where('slug', 'gece-mavisi')->sole()->config;
    expect($geceMavisi)->toMatchArray([
        'v' => 1,
        'paper' => '#1D2340',
        'ink' => '#FFFFFF',
        'accent' => '#D9B46A',
        'frame' => 'single',
        'ornament' => 'dot-rule',
    ]);

    // Tekrar çalıştırmak çoğaltmaz.
    $this->seed();
    expect(Template::count())->toBe(6)->and(Admin::count())->toBe(1);
});
