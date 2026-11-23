<?php

namespace App\Filament\Resources\Catalog\Extras;

use App\Domain\Catalog\Enums\ExtraPricingType;
use App\Domain\Catalog\Enums\VehicleType;
use App\Domain\Catalog\Services\ExtraService;
use App\Filament\Resources\Catalog\Extras\Pages\ManageExtras;
use App\Models\Extra;
use App\Models\Vehicle;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Repeater;
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
                Action::make('compatibility')
                    ->label('Compatibility')
                    ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                    ->color('info')
                    ->fillForm(fn (Extra $record): array => [
                        'compatibilities' => $record->compatibilities()->get()->map(fn ($c): array => [
                            'id' => $c->id,
                            'service_type' => $c->service_type,
                            'vehicle_type' => $c->vehicle_type,
                            'vehicle_id' => $c->vehicle_id,
                            'is_compatible' => $c->is_compatible,
                        ])->toArray(),
                    ])
                    ->form([
                        Repeater::make('compatibilities')
                            ->label('Compatibility Rules (4-Tier Hierarchy)')
                            ->helperText('Rules define where this extra is permitted or forbidden. If no rules exist, extra is globally compatible.')
                            ->schema([
                                Select::make('service_type')
                                    ->label('Service (Optional)')
                                    ->options([
                                        'pickup' => 'Airport Pickup',
                                        'transfer' => 'Airport Transfer',
                                        'ride' => 'Ride',
                                        'rent' => 'Rent',
                                    ])
                                    ->placeholder('All Services'),
                                Select::make('vehicle_type')
                                    ->label('Vehicle Type (Optional)')
                                    ->options(VehicleType::class)
                                    ->placeholder('All Vehicle Types'),
                                Select::make('vehicle_id')
                                    ->label('Exact Vehicle (Optional)')
                                    ->options(fn () => Vehicle::pluck('public_name', 'id'))
                                    ->placeholder('All Vehicles'),
                                Toggle::make('is_compatible')
                                    ->label('Compatible')
                                    ->default(true),
                            ])
                            ->columns(4),
                    ])
                    ->action(function (Extra $record, array $data): void {
                        $service = app(ExtraService::class);
                        $existing = $record->compatibilities()->get()->keyBy('id');
                        /** @var array<int, array<string, mixed>> $submitted */
                        $submitted = $data['compatibilities'] ?? [];

                        // Delete removed rules
                        $submittedIds = array_filter(array_column($submitted, 'id'));
                        foreach ($existing as $id => $rule) {
                            if (! in_array($id, $submittedIds, true)) {
                                $service->deleteCompatibilityRule($rule, auth()->user());
                            }
                        }

                        // Add or update rules
                        foreach ($submitted as $row) {
                            $ruleId = $row['id'] ?? null;
                            if ($ruleId && $existing->has($ruleId)) {
                                $rule = $existing->get($ruleId);
                                $rule->update([
                                    'service_type' => ! empty($row['service_type']) ? (string) $row['service_type'] : null,
                                    'vehicle_type' => ! empty($row['vehicle_type']) ? (string) $row['vehicle_type'] : null,
                                    'vehicle_id' => ! empty($row['vehicle_id']) ? (int) $row['vehicle_id'] : null,
                                    'is_compatible' => (bool) ($row['is_compatible'] ?? true),
                                ]);
                            } else {
                                $service->createCompatibilityRule($record, [
                                    'service_type' => ! empty($row['service_type']) ? (string) $row['service_type'] : null,
                                    'vehicle_type' => ! empty($row['vehicle_type']) ? (string) $row['vehicle_type'] : null,
                                    'vehicle_id' => ! empty($row['vehicle_id']) ? (int) $row['vehicle_id'] : null,
                                    'is_compatible' => (bool) ($row['is_compatible'] ?? true),
                                ], auth()->user());
                            }
                        }
                    }),
                EditAction::make()
                    ->using(fn (Extra $record, array $data) => app(ExtraService::class)->updateExtra($record, $data, auth()->user())),
                DeleteAction::make()
                    ->using(function (Extra $record): bool {
                        app(ExtraService::class)->deleteExtra($record, auth()->user());

                        return true;
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageExtras::route('/'),
        ];
    }
}
