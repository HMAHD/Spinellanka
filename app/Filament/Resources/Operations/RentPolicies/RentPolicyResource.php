<?php

declare(strict_types=1);

namespace App\Filament\Resources\Operations\RentPolicies;

use App\Domain\Identity\Permission;
use App\Filament\Resources\Operations\RentPolicies\Pages\ManageRentPolicies;
use App\Models\RentPolicy;
use BackedEnum;
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

class RentPolicyResource extends Resource
{
    protected static ?string $model = RentPolicy::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static UnitEnum|string|null $navigationGroup = 'Pricing & Policies';

    protected static ?string $navigationLabel = 'Rent Policies & Eligibility';

    public static function canViewAny(): bool
    {
        return auth()->user()?->hasPermissionTo(Permission::ConfigManage->value) ?? false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Driver Eligibility Rules')
                    ->description('Set legal and insurer-backed driver eligibility thresholds for self-drive vehicle hire.')
                    ->schema([
                        TextInput::make('min_driver_age')
                            ->label('Minimum Driver Age')
                            ->numeric()
                            ->required()
                            ->default(23),
                        TextInput::make('max_driver_age')
                            ->label('Maximum Driver Age')
                            ->numeric()
                            ->required()
                            ->default(70),
                        TextInput::make('min_licence_years')
                            ->label('Minimum Licence Held (Years)')
                            ->numeric()
                            ->required()
                            ->default(2),
                        Toggle::make('idp_required')
                            ->label('International Driving Permit (IDP) Mandatory')
                            ->default(true),
                        TextInput::make('max_additional_drivers')
                            ->label('Maximum Additional Drivers')
                            ->numeric()
                            ->required()
                            ->default(1),
                    ])
                    ->columns(2),

                Section::make('24-Hour Cycle & Timing Boundaries')
                    ->description('Duration calculation parameters in Asia/Colombo.')
                    ->schema([
                        TextInput::make('grace_period_minutes')
                            ->label('Grace Period (Minutes)')
                            ->numeric()
                            ->required()
                            ->default(59)
                            ->helperText('Returns up to this many minutes past 24h cycle are free.'),
                        TextInput::make('extra_hours_threshold')
                            ->label('Extra Hours Threshold (Hours)')
                            ->numeric()
                            ->required()
                            ->default(5)
                            ->helperText('1 to N extra hours billed at hourly rate.'),
                        TextInput::make('full_day_threshold_hours')
                            ->label('Full Day Round-Up Threshold (Hours)')
                            ->numeric()
                            ->required()
                            ->default(6)
                            ->helperText('Returns past this threshold round up to a full extra 24h day.'),
                    ])
                    ->columns(3),

                Section::make('Deposits, Insurance & Mileage')
                    ->description('Financial guarantees, excess limits, and distance allowances.')
                    ->schema([
                        TextInput::make('security_deposit_minor_units')
                            ->label('Security Deposit (Cents USD)')
                            ->numeric()
                            ->required()
                            ->default(50000)
                            ->helperText('50000 = $500.00 USD pre-authorization.'),
                        TextInput::make('insurance_excess_minor_units')
                            ->label('Insurance Excess Cap (Cents USD)')
                            ->numeric()
                            ->required()
                            ->default(50000)
                            ->helperText('50000 = $500.00 USD maximum client deductible.'),
                        TextInput::make('daily_mileage_km')
                            ->label('Daily Mileage Allowance (KM)')
                            ->numeric()
                            ->required()
                            ->default(100)
                            ->helperText('Cumulative allowance per rental day.'),
                        TextInput::make('excess_mileage_rate_minor_units')
                            ->label('Excess Mileage Rate (Cents USD/KM)')
                            ->numeric()
                            ->required()
                            ->default(30)
                            ->helperText('30 = $0.30 USD per excess km at return.'),
                    ])
                    ->columns(2),

                Section::make('Handover Locations & Restrictions')
                    ->schema([
                        Toggle::make('office_handover_available')
                            ->label('Colombo Office Handover Available')
                            ->default(true),
                        Toggle::make('airport_handover_available')
                            ->label('Airport (BIA/CMB) Handover Available')
                            ->default(true),
                        Toggle::make('hotel_delivery_available')
                            ->label('Hotel Delivery in Colombo Available')
                            ->default(true),
                        TextInput::make('hotel_delivery_fee_minor_units')
                            ->label('Hotel Delivery Fee (Cents USD)')
                            ->numeric()
                            ->default(2000)
                            ->helperText('2000 = $20.00 USD.'),
                        TextInput::make('prohibited_roads_text')
                            ->label('Prohibited Road Usage Copy')
                            ->default('Paved public roads only; off-road driving strictly prohibited.')
                            ->columnSpanFull(),
                        TextInput::make('smoking_penalty_minor_units')
                            ->label('Deep Cleaning / Smoking Penalty (Cents USD)')
                            ->numeric()
                            ->default(15000)
                            ->helperText('15000 = $150.00 USD.'),
                        Toggle::make('is_active')
                            ->label('Active Policy')
                            ->default(true),
                    ])
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->sortable(),
                TextColumn::make('driver_age_range')
                    ->label('Driver Age')
                    ->state(fn (RentPolicy $record) => "{$record->min_driver_age}–{$record->max_driver_age} yrs"),
                TextColumn::make('min_licence_years')
                    ->label('Min Licence')
                    ->formatStateUsing(fn ($state) => "{$state}+ yrs"),
                IconColumn::make('idp_required')
                    ->label('IDP Req')
                    ->boolean(),
                TextColumn::make('security_deposit_minor_units')
                    ->label('Deposit (USD)')
                    ->formatStateUsing(fn ($state) => '$'.number_format($state / 100, 2)),
                TextColumn::make('insurance_excess_minor_units')
                    ->label('Excess Cap (USD)')
                    ->formatStateUsing(fn ($state) => '$'.number_format($state / 100, 2)),
                TextColumn::make('daily_mileage_km')
                    ->label('Allowance')
                    ->formatStateUsing(fn ($state) => "{$state} km/day"),
                IconColumn::make('is_active')
                    ->label('Active')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->dateTime('M j, Y H:i')
                    ->label('Last Updated'),
            ])
            ->defaultSort('id', 'desc')
            ->paginationMode(PaginationMode::Simple);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageRentPolicies::route('/'),
        ];
    }
}
