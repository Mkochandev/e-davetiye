<?php

namespace App\Filament\Salon\Resources\SalonUsers;

use App\Enums\SalonUserRole;
use App\Filament\Salon\CurrentSalon;
use App\Filament\Salon\Resources\SalonUsers\Pages\ManageSalonUsers;
use App\Models\SalonUser;
use App\Rules\Phone;
use App\Services\PhoneNumber;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\Rules\Unique;

/**
 * Yalnız owner: personel ekle, devre dışı bırak (spec §7).
 */
class SalonUserResource extends Resource
{
    protected static ?string $model = SalonUser::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $modelLabel = 'kullanıcı';

    protected static ?string $pluralModelLabel = 'kullanıcılar';

    protected static ?string $slug = 'kullanicilar';

    protected static ?int $navigationSort = 9;

    public static function canAccess(): bool
    {
        return CurrentSalon::isOwner();
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('salon_id', CurrentSalon::id());
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->label('Ad soyad')->required()->maxLength(120),
            TextInput::make('email')
                ->label('E-posta')
                ->email()
                ->required()
                ->maxLength(255)
                ->unique(ignoreRecord: true),
            TextInput::make('phone')
                ->label('Telefon')
                ->tel()
                ->rule(new Phone)
                ->dehydrateStateUsing(fn (?string $state) => app(PhoneNumber::class)->tryNormalize($state))
                ->unique(ignoreRecord: true, modifyRuleUsing: fn (Unique $rule, ?string $state) => $rule->where(
                    'phone', app(PhoneNumber::class)->tryNormalize($state) ?? $state,
                ))
                ->helperText('İsteğe bağlı; personel telefonla da giriş yapabilir.'),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Ad soyad')->searchable(),
                TextColumn::make('email')->label('E-posta'),
                TextColumn::make('phone')->label('Telefon')->placeholder('—'),
                TextColumn::make('role')->label('Rol')->badge()
                    ->formatStateUsing(fn (SalonUserRole $state) => $state === SalonUserRole::Owner ? 'Sahip' : 'Personel'),
                IconColumn::make('is_active')->label('Aktif')->boolean(),
                TextColumn::make('last_login_at')->label('Son giriş')
                    ->since()
                    ->placeholder('Henüz giriş yapmadı'),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('toggleActive')
                    ->label(fn (SalonUser $record) => $record->is_active ? 'Devre dışı bırak' : 'Etkinleştir')
                    ->color(fn (SalonUser $record) => $record->is_active ? 'danger' : 'success')
                    ->requiresConfirmation()
                    ->visible(fn (SalonUser $record) => $record->id !== CurrentSalon::user()->id)
                    ->action(fn (SalonUser $record) => $record->update(['is_active' => ! $record->is_active])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageSalonUsers::route('/'),
        ];
    }
}
