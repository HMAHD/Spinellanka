<?php

namespace App\Filament\Resources\Catalog\Vehicles;

use App\Domain\Catalog\Enums\TransmissionType;
use App\Domain\Catalog\Enums\VehicleStatus;
use App\Domain\Catalog\Enums\VehicleType;
use App\Filament\Resources\Catalog\Vehicles\Pages\CreateVehicle;
use App\Filament\Resources\Catalog\Vehicles\Pages\EditVehicle;
use App\Filament\Resources\Catalog\Vehicles\Pages\ListVehicles;
use App\Filament\Resources\Catalog\Vehicles\RelationManagers\DriverAssignmentsRelationManager;
use App\Filament\Resources\Catalog\Vehicles\RelationManagers\MediaRelationManager;
use App\Models\Vehicle;
use BackedEnum;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\PaginationMode;
use Filament\Tables\Table;
use UnitEnum;

class VehicleResource extends Resource
{
    protected static ?string $model = Vehicle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTruck;

    protected static UnitEnum|string|null $navigationGroup = 'Catalog & Fleet';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Tabs::make('Vehicle Information')
                    ->tabs([
                        Tab::make('Public Specifications')
                            ->schema([
                                TextInput::make('public_name')
                                    ->label('Public Display Name')
                                    ->required()
                                    ->maxLength(255)
                                    ->helperText('Approved commercial vehicle title visible to travelers.'),
                                TextInput::make('make')
                                    ->required()
                                    ->maxLength(100),
                                TextInput::make('model')
                                    ->required()
                                    ->maxLength(100),
                                TextInput::make('year')
                                    ->numeric()
                                    ->required()
                                    ->minValue(2000)
                                    ->maxValue(2100),
                                Select::make('vehicle_type')
                                    ->options(VehicleType::class)
                                    ->required(),
                                TextInput::make('passenger_capacity')
                                    ->numeric()
                                    ->required()
                                    ->minValue(1)
                                    ->maxValue(50),
                                TextInput::make('luggage_capacity')
                                    ->numeric()
                                    ->required()
                                    ->minValue(0)
                                    ->maxValue(50),
                                Select::make('transmission')
                                    ->options(TransmissionType::class)
                                    ->required(),
                                Toggle::make('has_ac')
                                    ->label('Air Conditioning')
                                    ->default(true),
                                TagsInput::make('accessibility_features')
                                    ->label('Accessibility Features')
                                    ->placeholder('Add feature (e.g. wheelchair ramp)'),
                                TagsInput::make('amenities')
                                    ->label('Vehicle Amenities')
                                    ->placeholder('Add amenity (e.g. Wi-Fi, rear screens)'),
                            ]),

                        Tab::make('Service Eligibility')
                            ->schema([
                                Section::make('Eligible Services')
                                    ->description('Configure which travel mobility services this vehicle supports.')
                                    ->schema([
                                        Toggle::make('eligible_for_pickup')
                                            ->label('Airport Pickup (Arrival)')
                                            ->helperText('Chauffeured arrival transfer with meet & greet.'),
                                        Toggle::make('eligible_for_transfer')
                                            ->label('Airport Transfer (Departure)')
                                            ->helperText('Chauffeured departure transfer to airport.'),
                                        Toggle::make('eligible_for_ride')
                                            ->label('Multi-Stop Ride')
                                            ->helperText('Chauffeured point-to-point or multi-stop travel.'),
                                        Toggle::make('eligible_for_rent')
                                            ->label('Self-Drive Rent')
                                            ->helperText('Self-drive vehicle hire without driver.'),
                                    ]),
                            ]),

                        Tab::make('Operational & Turnaround')
                            ->schema([
                                TextInput::make('turnaround_buffer_minutes')
                                    ->label('Turnaround Buffer (Minutes)')
                                    ->numeric()
                                    ->required()
                                    ->default(90)
                                    ->minValue(30)
                                    ->maxValue(720)
                                    ->helperText('Buffer time required between trips for cleaning, inspection, and repositioning (30–720 min).'),
                                Select::make('status')
                                    ->options(VehicleStatus::class)
                                    ->required()
                                    ->default(VehicleStatus::Active->value),
                            ]),

                        Tab::make('Internal Details & Compliance')
                            ->schema([
                                TextInput::make('internal_fleet_id')
                                    ->label('Internal Fleet ID')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(50)
                                    ->helperText('Internal company fleet identifier (never shown to public).'),
                                TextInput::make('license_plate')
                                    ->label('License Plate')
                                    ->required()
                                    ->unique(ignoreRecord: true)
                                    ->maxLength(50),
                                TextInput::make('vin')
                                    ->label('VIN')
                                    ->maxLength(100),
                                DatePicker::make('registration_expiry')
                                    ->label('Registration Expiry'),
                                DatePicker::make('insurance_expiry')
                                    ->label('Insurance Expiry'),
                                Textarea::make('internal_notes')
                                    ->label('Internal Operational Notes')
                                    ->rows(3),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('public_name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),
                TextColumn::make('internal_fleet_id')
                    ->badge()
                    ->label('Fleet ID')
                    ->searchable(),
                TextColumn::make('vehicle_type')
                    ->badge()
                    ->label('Type'),
                TextColumn::make('passenger_capacity')
                    ->label('Pax')
                    ->alignCenter(),
                TextColumn::make('luggage_capacity')
                    ->label('Bags')
                    ->alignCenter(),
                IconColumn::make('eligible_for_pickup')
                    ->boolean()
                    ->label('Pickup'),
                IconColumn::make('eligible_for_transfer')
                    ->boolean()
                    ->label('Transfer'),
                IconColumn::make('eligible_for_ride')
                    ->boolean()
                    ->label('Ride'),
                IconColumn::make('eligible_for_rent')
                    ->boolean()
                    ->label('Rent'),
                TextColumn::make('turnaround_buffer_minutes')
                    ->label('Buffer')
                    ->suffix(' min')
                    ->alignCenter(),
                TextColumn::make('status')
                    ->badge(),
            ])
            ->paginationMode(PaginationMode::Simple)
            ->recordActions([
                EditAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            MediaRelationManager::class,
            DriverAssignmentsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVehicles::route('/'),
            'create' => CreateVehicle::route('/create'),
            'edit' => EditVehicle::route('/{record}/edit'),
        ];
    }
}
