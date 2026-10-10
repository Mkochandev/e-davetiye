<?php

use App\Enums\SalonUserRole;
use App\Filament\Admin\Resources\Events\EventResource;
use App\Filament\Admin\Resources\Purchases\PurchaseResource;
use App\Filament\Admin\Resources\Salons\Pages\CreateSalon;
use App\Filament\Admin\Resources\Salons\SalonResource;
use App\Filament\Admin\Resources\Templates\Pages\CreateTemplate;
use App\Filament\Admin\Resources\Templates\Pages\EditTemplate;
use App\Filament\Admin\Resources\Templates\TemplateResource;
use App\Filament\Admin\Resources\Users\UserResource;
use App\Models\Admin;
use App\Models\Salon;
use App\Models\SalonUser;
use App\Models\Template;
use App\Notifications\SalonPasswordSetup;
use Database\Seeders\TemplateSeeder;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs(Admin::factory()->create(), 'admin');
});

it('renders every admin page', function () {
    $this->seed(TemplateSeeder::class);

    foreach ([
        '/admin',
        SalonResource::getUrl(),
        SalonResource::getUrl('create'),
        TemplateResource::getUrl(),
        TemplateResource::getUrl('edit', ['record' => Template::first()]),
        UserResource::getUrl(),
        EventResource::getUrl(),
        PurchaseResource::getUrl(),
    ] as $url) {
        $this->get($url)->assertOk();
    }
});

it('requires an admin session', function () {
    auth('admin')->logout();

    $this->get('/admin')->assertRedirect('/admin/login');
});

it('creates a salon with its owner and emails a password setup link', function () {
    Notification::fake();

    Livewire::test(CreateSalon::class)
        ->fillForm([
            'name' => 'Yeni Salon',
            'district' => 'Şehitkamil',
            'phone' => '0342 333 44 55',
            'owner_name' => 'Mehmet Demir',
            'owner_email' => 'Mehmet@YeniSalon.test',
            'owner_phone' => '0533 444 55 66',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $salon = Salon::sole();
    $owner = SalonUser::sole();

    expect($salon->phone)->toBe('+903423334455')
        ->and($owner->salon_id)->toBe($salon->id)
        ->and($owner->role)->toBe(SalonUserRole::Owner)
        ->and($owner->email)->toBe('mehmet@yenisalon.test')
        ->and($owner->phone)->toBe('+905334445566')
        ->and($owner->password)->toBeNull();

    Notification::assertSentTo($owner, SalonPasswordSetup::class, function (SalonPasswordSetup $n) use ($owner) {
        $url = $n->url($owner);

        // Bağlantı salon panelinin şifre belirleme sayfasına gider ve açılır.
        expect($url)->toContain('/salon/password-reset/reset');
        auth('admin')->logout();
        test()->get($url)->assertOk();

        return true;
    });
});

it('rejects a duplicate owner email', function () {
    SalonUser::factory()->create(['email' => 'var@test.test']);

    Livewire::test(CreateSalon::class)
        ->fillForm(['name' => 'Salon', 'owner_name' => 'X', 'owner_email' => 'var@test.test'])
        ->call('create')
        ->assertHasFormErrors(['owner_email']);
});

it('validates template config JSON against the v1 schema', function () {
    Livewire::test(CreateTemplate::class)
        ->fillForm([
            'name' => 'Deneme',
            'slug' => 'deneme',
            'config' => json_encode(['v' => 1, 'paper' => 'kırmızı', 'frame' => 'triple']),
        ])
        ->call('create')
        ->assertHasFormErrors(['config']);

    expect(Template::count())->toBe(0);
});

it('creates and edits templates', function () {
    Livewire::test(CreateTemplate::class)
        ->fillForm(['name' => 'Deneme', 'slug' => 'deneme', 'is_premium' => true])
        ->call('create')
        ->assertHasNoFormErrors();

    $template = Template::sole();
    expect($template->is_premium)->toBeTrue()
        ->and($template->config['frame'])->toBe('double')
        ->and($template->event_types)->toHaveCount(6);

    $config = [...$template->config, 'ornament' => 'top-band'];

    Livewire::test(EditTemplate::class, ['record' => $template->getRouteKey()])
        ->fillForm(['config' => json_encode($config)])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($template->refresh()->config['ornament'])->toBe('top-band');
});
