<?php

declare(strict_types=1);

namespace App\Filament\Resources\Operations\ScheduleBlocks;

use App\Domain\Availability\Enums\ScheduleBlockKind;
use App\Domain\Availability\Enums\ScheduleBlockStatus;
use App\Domain\Availability\Services\AvailabilityService;
use App\Domain\Catalog\Enums\DriverStatus;
use App\Domain\Catalog\Enums\VehicleStatus;
use App\Domain\Identity\Permission;
use App\Filament\Resources\Operations\ScheduleBlocks\Pages\ManageScheduleBlocks;
use App\Models\Driver;
use App\Models\Vehicle;
use App\Models\VehicleScheduleBlock;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
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
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class ScheduleBlockResource extends Resource
{
    protected static ?string $model = VehicleScheduleBlock::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static UnitEnum|string|null $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Schedule Blocks';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(Permission::FleetManage->value) ?? false;
    }

    public static function canView(Model $record): bool
    {
        return auth()->user()?->can(Permission::FleetManage->value) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can(Permission::FleetManage->value) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Schedule Block Specification')
                    ->description('Define authoritative vehicle reservation or blocking interval.')
                    ->schema([
                        Select::make('vehicle_id')
                            ->label('Vehicle')
                            ->options(fn () => Vehicle::where('status', '!=', VehicleStatus::Retired->value)->pluck('public_name', 'id'))
                            ->searchable()
                            ->required(),
                        Select::make('kind')
                            ->label('Block Kind')
                            ->options([
                                ScheduleBlockKind::Maintenance->value => 'Maintenance',
                                ScheduleBlockKind::ManualBlock->value => 'Manual Operational Block',
                                ScheduleBlockKind::DriverUnavailability->value => 'Driver Unavailability (Chauffeured only)',
                            ])
                            ->default(ScheduleBlockKind::Maintenance->value)
                            ->required()
                            ->reactive(),
                        Select::make('driver_id')
                            ->label('Unavailable Driver')
                            ->options(fn () => Driver::where('status', DriverStatus::Active->value)->pluck('public_name', 'id'))
                            ->searchable()
                            ->visible(fn (callable $get): bool => $get('kind') === ScheduleBlockKind::DriverUnavailability->value)
                            ->required(fn (callable $get): bool => $get('kind') === ScheduleBlockKind::DriverUnavailability->value)
                            ->helperText('Tied driver whose unavailability blocks chauffeured services (Rent remains available).'),
                        DateTimePicker::make('starts_at')
                            ->label('Starts At (Asia/Colombo)')
                            ->timezone('Asia/Colombo')
                            ->required(),
                        DateTimePicker::make('ends_at')
                            ->label('Ends At (Asia/Colombo)')
                            ->timezone('Asia/Colombo')
                            ->after('starts_at')
                            ->required(),
                        TextInput::make('buffer_minutes_before')
                            ->label('Buffer Before (minutes)')
                            ->numeric()
                            ->default(0)
                            ->minValue(0),
                        TextInput::make('buffer_minutes_after')
                            ->label('Buffer After (minutes)')
                            ->numeric()
                            ->default(0)
                            ->minValue(0)
                            ->helperText('Leave 0 to use vehicle turnaround buffer default.'),
                        Textarea::make('notes')
                            ->label('Reason / Operational Notes')
                            ->maxLength(500)
                            ->rows(2),
                    ]),

                Section::make('Conflict Override Authorization')
                    ->description('If interval overlaps an existing reservation, override requires booking.override_conflict permission and recorded reason.')
                    ->schema([
                        Toggle::make('is_override')
                            ->label('Authorize Conflict Override')
                            ->default(false)
                            ->reactive()
                            ->disabled(fn (): bool => ! (auth()->user()?->can(Permission::BookingOverrideConflict->value) ?? false)),
                        Textarea::make('override_reason')
                            ->label('Override Reason & Authority')
                            ->visible(fn (callable $get): bool => (bool) $get('is_override'))
                            ->required(fn (callable $get): bool => (bool) $get('is_override'))
                            ->rows(2)
                            ->helperText('Audited justification for overriding vehicle schedule conflict.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('vehicle.public_name')
                    ->label('Vehicle')
                    ->sortable()
                    ->searchable(),
                TextColumn::make('kind')
                    ->badge()
                    ->formatStateUsing(fn ($state) => $state instanceof ScheduleBlockKind ? $state->label() : (ScheduleBlockKind::tryFrom((string) $state)?->label() ?? (string) $state)),
                TextColumn::make('status')
                    ->badge()
                    ->color(fn ($state): string => match ($state instanceof ScheduleBlockStatus ? $state : ScheduleBlockStatus::tryFrom((string) $state)) {
                        ScheduleBlockStatus::Active => 'success',
                        ScheduleBlockStatus::Released => 'gray',
                        ScheduleBlockStatus::Expired => 'warning',
                        default => 'danger',
                    }),
                TextColumn::make('effective_start')
                    ->label('Effective Start')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                TextColumn::make('effective_end')
                    ->label('Effective End')
                    ->dateTime('Y-m-d H:i')
                    ->sortable(),
                IconColumn::make('blocks_availability')
                    ->label('Blocks')
                    ->boolean(),
                IconColumn::make('is_chauffeured_only')
                    ->label('Chauffeured Only')
                    ->boolean()
                    ->toggleable(isToggledHiddenByDefault: true),
                IconColumn::make('is_override')
                    ->label('Override')
                    ->boolean(),
                TextColumn::make('notes')
                    ->label('Notes')
                    ->limit(25)
                    ->toggleable(),
            ])
            ->defaultSort('effective_start', 'desc')
            ->paginationMode(PaginationMode::Simple)
            ->filters([
                SelectFilter::make('vehicle_id')
                    ->label('Vehicle')
                    ->relationship('vehicle', 'public_name'),
                SelectFilter::make('status')
                    ->options(ScheduleBlockStatus::class),
                SelectFilter::make('kind')
                    ->options(ScheduleBlockKind::class),
            ])
            ->actions([
                ViewAction::make(),
                Action::make('release')
                    ->label('Release')
                    ->color('danger')
                    ->icon(Heroicon::OutlinedLockOpen)
                    ->visible(fn (VehicleScheduleBlock $record): bool => $record->status === ScheduleBlockStatus::Active && $record->kind->isStaffCreatable() && (auth()->user()?->can(Permission::FleetManage->value) ?? false))
                    ->requiresConfirmation()
                    ->form([
                        TextInput::make('reason')
                            ->label('Release Reason')
                            ->required()
                            ->default('manual_staff_release'),
                    ])
                    ->action(function (VehicleScheduleBlock $record, array $data): void {
                        app(AvailabilityService::class)->releaseBlock(
                            block: $record,
                            reason: (string) ($data['reason'] ?? 'manual_staff_release'),
                            actor: auth()->user()
                        );
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageScheduleBlocks::route('/'),
        ];
    }
}
