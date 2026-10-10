<?php

namespace App\Filament\Admin\Resources\Templates;

use App\Enums\EventType;
use App\Filament\Admin\Resources\Templates\Pages\CreateTemplate;
use App\Filament\Admin\Resources\Templates\Pages\EditTemplate;
use App\Filament\Admin\Resources\Templates\Pages\ListTemplates;
use App\Models\Template;
use App\Support\TemplateConfig;
use BackedEnum;
use Closure;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\CodeEditor;
use Filament\Forms\Components\CodeEditor\Enums\Language;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class TemplateResource extends Resource
{
    protected static ?string $model = Template::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSwatch;

    protected static ?string $modelLabel = 'şablon';

    protected static ?string $pluralModelLabel = 'şablonlar';

    protected static ?int $navigationSort = 2;

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()
                ->columns(2)
                ->schema([
                    TextInput::make('name')->label('Ad')->required()->maxLength(100),
                    TextInput::make('slug')->label('Slug')->required()->alphaDash()->maxLength(100)
                        ->unique(ignoreRecord: true),
                    CheckboxList::make('event_types')
                        ->label('Etkinlik türleri')
                        ->options(collect(EventType::cases())->mapWithKeys(fn (EventType $t) => [$t->value => $t->label()]))
                        ->default(array_column(EventType::cases(), 'value'))
                        ->columns(3)
                        ->required()
                        ->columnSpanFull(),
                    Toggle::make('is_premium')->label('Premium (Etkinlik Paketi gerekir)'),
                    Toggle::make('is_active')->label('Aktif')->default(true),
                    TextInput::make('sort_order')->label('Sıra')->numeric()->integer()->default(0),
                ]),
            Section::make('Config (v1)')
                ->description('Renkler #RRGGBB; frame: none/single/double; ornament: none/dot-rule/top-band; fontlar: Fraunces, Plus Jakarta Sans.')
                ->schema([
                    CodeEditor::make('config')
                        ->hiddenLabel()
                        ->language(Language::Json)
                        ->required()
                        ->formatStateUsing(fn ($state) => is_array($state)
                            ? json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                            : $state)
                        ->default(json_encode([
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
                        ], JSON_PRETTY_PRINT))
                        ->rule(fn () => function (string $attribute, mixed $value, Closure $fail) {
                            foreach (TemplateConfig::errors(json_decode((string) $value, true)) as $error) {
                                $fail($error);
                            }
                        })
                        ->dehydrateStateUsing(fn (?string $state) => json_decode((string) $state, true)),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->reorderable('sort_order')
            ->columns([
                TextColumn::make('sort_order')->label('#'),
                TextColumn::make('name')->label('Ad')->searchable(),
                TextColumn::make('slug')->label('Slug'),
                IconColumn::make('is_premium')->label('Premium')->boolean(),
                ToggleColumn::make('is_active')->label('Aktif'),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTemplates::route('/'),
            'create' => CreateTemplate::route('/create'),
            'edit' => EditTemplate::route('/{record}/edit'),
        ];
    }
}
