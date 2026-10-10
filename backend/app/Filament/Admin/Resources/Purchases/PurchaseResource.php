<?php

namespace App\Filament\Admin\Resources\Purchases;

use App\Enums\Platform;
use App\Enums\PurchaseStatus;
use App\Filament\Admin\Resources\Purchases\Pages\ListPurchases;
use App\Models\Purchase;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PurchaseResource extends Resource
{
    protected static ?string $model = Purchase::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShoppingBag;

    protected static ?string $modelLabel = 'satın alma';

    protected static ?string $pluralModelLabel = 'satın almalar';

    protected static ?int $navigationSort = 5;

    public static function canCreate(): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['user', 'event']))
            ->columns([
                TextColumn::make('created_at')->label('Tarih')->dateTime('d.m.Y H:i', 'Europe/Istanbul')->sortable(),
                TextColumn::make('user.phone')->label('Kullanıcı')->placeholder('Silinmiş hesap'),
                TextColumn::make('event.title')->label('Etkinlik')->placeholder('—'),
                TextColumn::make('platform')->label('Platform')->formatStateUsing(fn (Platform $state) => $state->name),
                TextColumn::make('transaction_id')->label('İşlem')->searchable()->limit(24),
                TextColumn::make('status')->label('Durum')->badge()
                    ->color(fn (PurchaseStatus $state) => match ($state) {
                        PurchaseStatus::Verified => 'success',
                        PurchaseStatus::Failed => 'danger',
                        PurchaseStatus::Refunded => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')->label('Durum')
                    ->options(collect(PurchaseStatus::cases())->mapWithKeys(fn (PurchaseStatus $s) => [$s->value => $s->value])),
                SelectFilter::make('platform')->label('Platform')
                    ->options(collect(Platform::cases())->mapWithKeys(fn (Platform $p) => [$p->value => $p->name])),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPurchases::route('/'),
        ];
    }
}
