<?php

namespace App\Filament\Admin\Resources\Users;

use App\Filament\Admin\Resources\Users\Pages\ListUsers;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDevicePhoneMobile;

    protected static ?string $modelLabel = 'uygulama kullanıcısı';

    protected static ?string $pluralModelLabel = 'uygulama kullanıcıları';

    protected static ?int $navigationSort = 3;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->label('Ad')->placeholder('—')->searchable(),
                TextColumn::make('phone')->label('Telefon')->searchable(),
                TextColumn::make('events_count')->label('Etkinlik')->counts('events'),
                TextColumn::make('kvkk_accepted_at')->label('Açık rıza')->dateTime('d.m.Y H:i', 'Europe/Istanbul')->placeholder('—'),
                TextColumn::make('created_at')->label('Kayıt')->dateTime('d.m.Y H:i', 'Europe/Istanbul')->sortable(),
            ])
            ->filters([
                TernaryFilter::make('kvkk_accepted_at')->label('Profil tamamlandı')->nullable(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
        ];
    }
}
