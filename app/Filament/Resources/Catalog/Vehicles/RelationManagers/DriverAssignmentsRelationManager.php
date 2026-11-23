<?php

namespace App\Filament\Resources\Catalog\Vehicles\RelationManagers;

use App\Domain\Catalog\Enums\DriverStatus;
use App\Domain\Catalog\Services\DriverAssignmentService;
use App\Models\Driver;
use App\Models\Vehicle;
use App\Models\VehicleDriverAssignment;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

class DriverAssignmentsRelationManager extends RelationManager
{
    protected static string $relationship = 'driverAssignments';

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('driver_id')
                    ->label('Driver')
                    ->options(fn () => Driver::where('status', DriverStatus::Active->value)->pluck('public_name', 'id'))
                    ->searchable()
                    ->required(),
                DateTimePicker::make('starts_at')
                    ->label('Starts At (Asia/Colombo)')
                    ->timezone('Asia/Colombo')
                    ->required(),
                DateTimePicker::make('ends_at')
                    ->label('Ends At (Asia/Colombo)')
                    ->timezone('Asia/Colombo')
                    ->after('starts_at')
                    ->required(fn (callable $get): bool => ! (bool) $get('is_permanent'))
                    ->helperText('Required for temporary assignments. Leave empty for permanent base assignment.'),
                Toggle::make('is_permanent')
                    ->label('Permanent Base Driver')
                    ->default(false)
                    ->helperText('Mark as the default chauffeured driver for this exact vehicle.'),
                TextInput::make('reason')
                    ->label('Assignment Reason')
                    ->maxLength(255)
                    ->placeholder('e.g. Primary tied driver, vacation cover'),
            ]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('reason')
            ->columns([
                TextColumn::make('driver.public_name')
                    ->label('Driver')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('starts_at')
                    ->label('Starts At')
                    ->dateTime()
                    ->sortable(),
                TextColumn::make('ends_at')
                    ->label('Ends At')
                    ->dateTime()
                    ->placeholder('Permanent / Ongoing'),
                IconColumn::make('is_permanent')
                    ->boolean()
                    ->label('Permanent'),
                TextColumn::make('reason')
                    ->limit(30),
                TextColumn::make('assignedByUser.name')
                    ->label('Assigned By'),
            ])
            ->headerActions([
                CreateAction::make()
                    ->using(function (array $data, RelationManager $livewire): Model {
                        /** @var Vehicle $vehicle */
                        $vehicle = $livewire->getOwnerRecord();
                        $service = app(DriverAssignmentService::class);
                        $driver = Driver::findOrFail($data['driver_id']);
                        $startsAt = Carbon::parse($data['starts_at'], 'Asia/Colombo');

                        if ($data['is_permanent'] ?? false) {
                            return $service->assignPermanentDriver(
                                vehicle: $vehicle,
                                driver: $driver,
                                startsAt: $startsAt,
                                reason: $data['reason'] ?? null,
                                assignedBy: auth()->user()
                            );
                        }

                        if (empty($data['ends_at'])) {
                            throw new \InvalidArgumentException('Temporary driver assignment requires an end date and time.');
                        }

                        $endsAt = Carbon::parse($data['ends_at'], 'Asia/Colombo');

                        return $service->assignTemporaryDriver(
                            vehicle: $vehicle,
                            driver: $driver,
                            startsAt: $startsAt,
                            endsAt: $endsAt,
                            reason: $data['reason'] ?? null,
                            assignedBy: auth()->user()
                        );
                    }),
            ])
            ->recordActions([
                Action::make('end_assignment')
                    ->label('End Now')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->visible(function (VehicleDriverAssignment $record): bool {
                        $endsAt = $record->ends_at;

                        return $endsAt === null || Carbon::parse($endsAt)->isFuture();
                    })
                    ->requiresConfirmation()
                    ->modalHeading('End Driver Assignment')
                    ->modalDescription('Are you sure you want to end this driver assignment as of right now?')
                    ->action(fn (VehicleDriverAssignment $record) => app(DriverAssignmentService::class)->endAssignment($record, Carbon::now('Asia/Colombo'), auth()->user())),
            ]);
    }
}
