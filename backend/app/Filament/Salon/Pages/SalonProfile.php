<?php

namespace App\Filament\Salon\Pages;

use App\Filament\Salon\CurrentSalon;
use App\Rules\Phone;
use App\Services\PhoneNumber;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

/**
 * Yalnız owner (spec §7).
 */
class SalonProfile extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $navigationLabel = 'Salon profili';

    protected static ?string $title = 'Salon profili';

    protected static ?string $slug = 'profil';

    protected static ?int $navigationSort = 8;

    /** @var array<string, mixed>|null */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return CurrentSalon::isOwner();
    }

    public function mount(): void
    {
        $this->form->fill(CurrentSalon::get()->only([
            'name', 'phone', 'email', 'address', 'city', 'district', 'latitude', 'longitude', 'logo_path', 'capacity',
        ]));
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                Section::make()
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')->label('Salon adı')->required()->maxLength(255),
                        TextInput::make('capacity')->label('Kapasite (kişi)')->numeric()->minValue(1)->maxValue(100_000),
                        TextInput::make('phone')->label('Telefon')->tel()->rule(new Phone),
                        TextInput::make('email')->label('E-posta')->email()->maxLength(255),
                        TextInput::make('address')->label('Adres')->maxLength(255)->columnSpanFull(),
                        TextInput::make('city')->label('İl')->maxLength(100),
                        TextInput::make('district')->label('İlçe')->maxLength(100),
                        TextInput::make('latitude')->label('Enlem')->numeric()->minValue(-90)->maxValue(90),
                        TextInput::make('longitude')->label('Boylam')->numeric()->minValue(-180)->maxValue(180),
                        FileUpload::make('logo_path')
                            ->label('Logo')
                            ->image()
                            ->disk('public')
                            ->directory('salon-logos')
                            ->maxSize(2048)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([
            Form::make([EmbeddedSchema::make('form')])
                ->id('form')
                ->livewireSubmitHandler('save')
                ->footer([
                    Actions::make([
                        Action::make('save')->label('Kaydet')->submit('save'),
                    ]),
                ]),
        ]);
    }

    public function save(): void
    {
        abort_unless(static::canAccess(), 403);

        $data = $this->form->getState();
        $data['phone'] = app(PhoneNumber::class)->tryNormalize($data['phone'] ?? null);

        CurrentSalon::get()->update($data);

        Notification::make()->title('Salon profili kaydedildi')->success()->send();
    }
}
