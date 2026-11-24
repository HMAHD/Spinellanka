<?php

declare(strict_types=1);

namespace App\Filament\Resources\Operations\PricingRules;

use App\Domain\Booking\ServiceType;
use App\Domain\Catalog\Enums\VehicleStatus;
use App\Domain\Identity\Permission;
use App\Domain\Pricing\Enums\PricingComponentType;
use App\Domain\Pricing\Enums\PricingUnit;
use App\Domain\Pricing\Services\PricingRuleService;
use App\Filament\Resources\Operations\PricingRules\Pages\ManagePricingRules;
use App\Models\PricingRule;
use App\Models\Vehicle;
use BackedEnum;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use UnitEnum;

class PricingRuleResource extends Resource
{
    protected static ?string $model = PricingRule::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalculator;

    protected static UnitEnum|string|null $navigationGroup = 'Pricing & Policies';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermissionTo(Permission::ConfigManage->value) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Pricing Rule Scope & Validity')
                    ->description('Define service, vehicle applicability, priority, and effective date window in Asia/Colombo.')
                    ->schema([
                        Select::make('service_type')
                            ->label('Service Type')
                            ->options(ServiceType::class)
                            ->required(),
                        Select::make('vehicle_id')
                            ->label('Exact Vehicle (Optional)')
                            ->options(
                                Vehicle::where('status', '!=', VehicleStatus::Retired)
                                    ->pluck('public_name', 'id')
                            )
                            ->placeholder('Service-Wide Default (All eligible vehicles)')
                            ->searchable(),
                        TextInput::make('priority')
                            ->label('Resolution Priority')
                            ->numeric()
                            ->default(0)
                            ->helperText('Higher priority wins if date and scope match.')
                            ->required(),
                        Toggle::make('is_active')
                            ->label('Active for Quote Calculation')
                            ->default(true),
                        DateTimePicker::make('effective_from')
                            ->label('Effective From (Asia/Colombo)')
                            ->required()
                            ->default(now('Asia/Colombo')),
                        DateTimePicker::make('effective_to')
                            ->label('Effective To (Optional)')
                            ->helperText('Leave empty for open-ended ongoing pricing.'),
                        Textarea::make('notes')
                            ->label('Internal Operational Notes')
                            ->columnSpanFull(),
                    ])
                    ->columns(2),

                Section::make('Rule Components & Calculation Units')
                    ->description('Itemized flat rates, distance/duration units, percentage taxes, and deposits.')
                    ->schema([
                        Repeater::make('components')
                            ->relationship('components')
                            ->schema([
                                Select::make('component_type')
                                    ->label('Component')
                                    ->options(PricingComponentType::class)
                                    ->required(),
                                Select::make('unit')
                                    ->label('Unit')
                                    ->options(PricingUnit::class)
                                    ->live()
                                    ->required(),
                                TextInput::make('amount_display')
                                    ->label('Rate / Amount')
                                    ->numeric()
                                    ->required()
                                    ->prefix(fn (Get $get): ?string => $get('unit') === PricingUnit::Percentage->value ? null : '$')
                                    ->suffix(fn (Get $get): ?string => $get('unit') === PricingUnit::Percentage->value ? '%' : null)
                                    ->helperText(fn (Get $get): string => $get('unit') === PricingUnit::Percentage->value ? 'Percentage points (e.g. 15.00 for 15%)' : 'Dollar amount (e.g. 50.00 for $50)'),
                                TextInput::make('min_units')
                                    ->label('Min Units (Optional)')
                                    ->numeric(),
                                TextInput::make('max_units')
                                    ->label('Max Units (Optional)')
                                    ->numeric(),
                                Toggle::make('is_taxable')
                                    ->label('Taxable Base')
                                    ->default(false),
                                Toggle::make('is_deposit')
                                    ->label('Refundable Deposit')
                                    ->default(false),
                            ])
                            ->columns(3)
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('service_type')
                    ->badge(),
                TextColumn::make('vehicle.public_name')
                    ->placeholder('Service-Wide Default')
                    ->searchable(),
                TextColumn::make('version')
                    ->label('Ver'),
                TextColumn::make('priority')
                    ->sortable(),
                IconColumn::make('is_active')
                    ->boolean(),
                TextColumn::make('effective_from')
                    ->dateTime('M j, Y H:i')
                    ->sortable(),
                TextColumn::make('effective_to')
                    ->dateTime('M j, Y H:i')
                    ->placeholder('Ongoing'),
            ])
            ->filters([
                SelectFilter::make('service_type')
                    ->options(ServiceType::class),
            ])
            ->paginationMode(PaginationMode::Simple)
            ->recordActions([
                EditAction::make()
                    ->using(fn (PricingRule $record, array $data) => app(PricingRuleService::class)->updateRule($record, $data, auth()->user())),
                DeleteAction::make()
                    ->using(function (PricingRule $record): bool {
                        app(PricingRuleService::class)->deactivateRule($record, auth()->user());

                        return true;
                    })
                    ->label('Deactivate'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManagePricingRules::route('/'),
        ];
    }
}
