<?php

declare(strict_types=1);

namespace App\Filament\Resources\Offers\Promotions;

use App\Domain\Booking\Enums\PromotionDiscountType;
use App\Domain\Booking\Enums\PromotionScope;
use App\Domain\Booking\Enums\PromotionStatus;
use App\Domain\Booking\ServiceType;
use App\Domain\Identity\Permission;
use App\Filament\Resources\Offers\Promotions\Pages\ManagePromotions;
use App\Models\Promotion;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class PromotionResource extends Resource
{
    protected static ?string $model = Promotion::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static UnitEnum|string|null $navigationGroup = 'Offers & Promotions';

    protected static ?int $navigationSort = 2;

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermissionTo(Permission::OffersManage->value) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Promotion Details')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('code')
                            ->label('Promo Code (Optional)')
                            ->unique(ignoreRecord: true)
                            ->maxLength(64),
                        Textarea::make('explanation')
                            ->rows(2)
                            ->required()
                            ->helperText('Clear explanation for staff and guest statements.'),
                    ]),

                Section::make('Scope & Precedence')
                    ->description('Scope hierarchy: Deal > Service > Global. Highest priority wins within scope.')
                    ->schema([
                        Select::make('scope')
                            ->options(PromotionScope::class)
                            ->default(PromotionScope::Global->value)
                            ->required(),
                        Select::make('service_type')
                            ->options(ServiceType::class)
                            ->nullable(),
                        Select::make('deal_id')
                            ->relationship('deal', 'title')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        TextInput::make('priority')
                            ->numeric()
                            ->default(0)
                            ->required()
                            ->helperText('Tie-breaker priority. Multiple equal-priority active promos trigger unpromoted fallback.'),
                    ]),

                Section::make('Discount Terms & Validity')
                    ->schema([
                        Select::make('discount_type')
                            ->options(PromotionDiscountType::class)
                            ->required(),
                        TextInput::make('discount_value')
                            ->numeric()
                            ->required()
                            ->helperText('Minor units for fixed (e.g. 1500 = $15.00) or basis points for percentage (e.g. 1500 = 15.00%). Capped at service subtotal.'),
                        Select::make('currency')
                            ->options([
                                'USD' => 'USD',
                                'LKR' => 'LKR',
                                'EUR' => 'EUR',
                                'GBP' => 'GBP',
                            ])
                            ->default('USD'),
                        Select::make('status')
                            ->options(PromotionStatus::class)
                            ->default(PromotionStatus::Draft->value)
                            ->required(),
                        DateTimePicker::make('starts_at')
                            ->label('Starts At (Asia/Colombo)')
                            ->nullable(),
                        DateTimePicker::make('ends_at')
                            ->label('Ends At (Asia/Colombo)')
                            ->nullable(),
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
                TextColumn::make('code')
                    ->searchable(),
                TextColumn::make('scope')
                    ->badge(),
                TextColumn::make('discount_type')
                    ->badge(),
                TextColumn::make('discount_value')
                    ->sortable(),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn (PromotionStatus $state): string => match ($state) {
                        PromotionStatus::Active => 'success',
                        PromotionStatus::Draft => 'warning',
                        PromotionStatus::Archived => 'gray',
                    }),
                TextColumn::make('priority')
                    ->sortable(),
                TextColumn::make('starts_at')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->actions([
                EditAction::make(),
                DeleteAction::make(),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePromotions::route('/'),
        ];
    }
}
