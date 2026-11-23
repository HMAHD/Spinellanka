<?php

namespace App\Filament\Resources\Catalog\Extras;

use App\Domain\Catalog\Enums\ExtraPricingType;
use App\Filament\Resources\Catalog\Extras\Pages\ManageExtras;
use App\Models\Extra;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Table;
use UnitEnum;

class ExtraResource extends Resource
{
    protected static ?string $model = Extra::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static UnitEnum|string|null $navigationGroup = 'Catalog & Fleet';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Extra Details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Name of the optional add-on (e.g. Child Safety Seat).'),
                        TextInput::make('slug')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->maxLength(100),
                        Textarea::make('description')
                            ->rows(3),
                        TextInput::make('price_minor_units')
                            ->label('Price (USD)')
                            ->numeric()
                            ->prefix('$')
                            ->required()
                            ->formatStateUsing(fn (?int $state): ?string => $state !== null ? number_format($state / 100, 2, '.', '') : null)
                            ->dehydrateStateUsing(fn (?string $state): int => $state !== null ? (int) round(((float) $state) * 100) : 0),
                        Select::make('currency')
                            ->options(['USD' => 'USD (Primary Display)', 'LKR' => 'LKR (Approximate)'])
                            ->default('USD')
                            ->required(),
                        Select::make('pricing_type')
                            ->options(ExtraPricingType::class)
                            ->required()
                            ->default(ExtraPricingType::PerBooking->value),
                        TextInput::make('max_quantity')
                            ->numeric()
                            ->required()
                            ->default(1)
                            ->minValue(1)
                            ->maxValue(10),
                        Toggle::make('is_active')
                            ->label('Active / Available for Selection')
                            ->default(true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('slug')
                    ->searchable(),
                TextColumn::make('price_minor_units')
                    ->label('Price')
                    ->money('USD', divideBy: 100)
                    ->sortable(),
                TextColumn::make('pricing_type')
                    ->badge(),
                TextColumn::make('max_quantity')
                    ->label('Max Qty')
                    ->alignCenter(),
                IconColumn::make('is_active')
                    ->boolean()
                    ->label('Active'),
            ])
            ->paginationMode(PaginationMode::Simple)
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageExtras::route('/'),
        ];
    }
}
