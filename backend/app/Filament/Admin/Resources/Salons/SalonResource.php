<?php

namespace App\Filament\Admin\Resources\Salons;

use App\Enums\SalonUserRole;
use App\Filament\Admin\Resources\Salons\Pages\CreateSalon;
use App\Filament\Admin\Resources\Salons\Pages\EditSalon;
use App\Filament\Admin\Resources\Salons\Pages\ListSalons;
use App\Models\Salon;
use App\Models\SalonUser;
use App\Rules\Phone;
use App\Services\PhoneNumber;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class SalonResource extends Resource
{
    protected static ?string $model = Salon::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBuildingStorefront;

    protected static ?string $modelLabel = 'salon';

    protected static ?string $pluralModelLabel = 'salonlar';

    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        $phones = app(PhoneNumber::class);

        return $schema->components([
            Section::make('Salon')
                ->columns(2)
                ->schema([
                    TextInput::make('name')->label('Salon adı')->required()->maxLength(255),
                    TextInput::make('capacity')->label('Kapasite (kişi)')->numeric()->minValue(1)->maxValue(100_000),
                    TextInput::make('phone')->label('Telefon')->tel()->rule(new Phone)
                        ->dehydrateStateUsing(fn (?string $state) => $phones->tryNormalize($state)),
                    TextInput::make('email')->label('E-posta')->email()->maxLength(255),
                    TextInput::make('address')->label('Adres')->maxLength(255)->columnSpanFull(),
                    TextInput::make('city')->label('İl')->default('Gaziantep')->maxLength(100),
                    TextInput::make('district')->label('İlçe')->maxLength(100),
                    TextInput::make('latitude')->label('Enlem')->numeric()->minValue(-90)->maxValue(90),
                    TextInput::make('longitude')->label('Boylam')->numeric()->minValue(-180)->maxValue(180),
                    Toggle::make('is_active')->label('Aktif')->default(true),
                ]),
            Section::make('Salon sahibi')
                ->description('Hesap açılır ve şifre belirleme bağlantısı e-postayla gönderilir.')
                ->columns(3)
                ->visibleOn('create')
                ->schema([
                    TextInput::make('owner_name')->label('Ad soyad')->required()->maxLength(120),
                    TextInput::make('owner_email')->label('E-posta')->email()->required()->maxLength(255)
                        ->unique(table: 'salon_users', column: 'email'),
                    TextInput::make('owner_phone')->label('Telefon')->tel()->rule(new Phone)
                        ->rule(fn () => function (string $attribute, mixed $value, \Closure $fail) use ($phones) {
                            if (SalonUser::where('phone', $phones->tryNormalize($value))->exists()) {
                                $fail('Bu telefon numarası başka bir salon kullanıcısında kayıtlı.');
                            }
                        }),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('name')
            ->columns([
                TextColumn::make('name')->label('Salon')->searchable()->sortable(),
                TextColumn::make('district')->label('İlçe'),
                TextColumn::make('owner')
                    ->label('Sahip')
                    ->state(fn (Salon $record) => $record->users->firstWhere('role', SalonUserRole::Owner)?->email),
                TextColumn::make('reservations_count')->label('Rezervasyon')->counts('reservations'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('created_at')->label('Açılış')->date('d.m.Y'),
            ])
            ->modifyQueryUsing(fn ($query) => $query->with('users'))
            ->filters([
                TernaryFilter::make('is_active')->label('Aktif'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('resendOwnerInvite')
                    ->label('Şifre e-postasını tekrar gönder')
                    ->icon('heroicon-o-envelope')
                    ->requiresConfirmation()
                    ->visible(fn (Salon $record) => $record->users->contains('role', SalonUserRole::Owner))
                    ->action(function (Salon $record) {
                        $record->users->firstWhere('role', SalonUserRole::Owner)->sendPasswordSetupLink();
                        Notification::make()->title('E-posta gönderildi')->success()->send();
                    }),
                Action::make('toggleActive')
                    ->label(fn (Salon $record) => $record->is_active ? 'Pasifleştir' : 'Aktifleştir')
                    ->color(fn (Salon $record) => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->action(fn (Salon $record) => $record->update(['is_active' => ! $record->is_active])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSalons::route('/'),
            'create' => CreateSalon::route('/create'),
            'edit' => EditSalon::route('/{record}/edit'),
        ];
    }
}
