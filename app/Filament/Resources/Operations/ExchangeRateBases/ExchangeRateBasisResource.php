<?php

declare(strict_types=1);

namespace App\Filament\Resources\Operations\ExchangeRateBases;

use App\Domain\Identity\Permission;
use App\Filament\Resources\Operations\ExchangeRateBases\Pages\ManageExchangeRateBases;
use App\Models\ExchangeRateBasis;
use BackedEnum;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Table;
use UnitEnum;

class ExchangeRateBasisResource extends Resource
{
    protected static ?string $model = ExchangeRateBasis::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCurrencyDollar;

    protected static UnitEnum|string|null $navigationGroup = 'Pricing & Policies';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermissionTo(Permission::ConfigManage->value) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Manual USD/LKR Approximation Basis')
                    ->description('Staff-maintained indicative exchange rate for customer approximate LKR display. Does not affect USD payment authority.')
                    ->schema([
                        TextInput::make('rate')
                            ->label('Rate (LKR per 1 USD)')
                            ->numeric()
                            ->required()
                            ->helperText('e.g. 315.5000')
                            ->placeholder('315.5000'),
                        TextInput::make('source_note')
                            ->label('Source & Attribution Note')
                            ->required()
                            ->maxLength(255)
                            ->helperText('e.g. Central Bank of Sri Lanka indicative rate 2026-11-24')
                            ->placeholder('Central Bank of Sri Lanka indicative rate'),
                        DateTimePicker::make('effective_at')
                            ->label('Effective At (Asia/Colombo)')
                            ->required()
                            ->default(now('Asia/Colombo')),
                    ])
                    ->columns(1),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('rate')
                    ->label('Rate (LKR/USD)')
                    ->sortable(),
                TextColumn::make('source_note')
                    ->label('Source Attribution')
                    ->searchable(),
                TextColumn::make('effective_at')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
                TextColumn::make('creator.name')
                    ->label('Entered By'),
                TextColumn::make('created_at')
                    ->dateTime('M j, Y H:i')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('effective_at', 'desc')
            ->paginationMode(PaginationMode::Simple);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageExchangeRateBases::route('/'),
        ];
    }
}
