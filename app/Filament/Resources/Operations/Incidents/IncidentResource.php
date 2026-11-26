<?php

declare(strict_types=1);

namespace App\Filament\Resources\Operations\Incidents;

use App\Domain\Identity\Permission;
use App\Domain\Operations\Enums\IncidentFinancialEntryType;
use App\Domain\Operations\Enums\IncidentSeverity;
use App\Domain\Operations\Enums\IncidentStatus;
use App\Domain\Operations\Enums\IncidentType;
use App\Domain\Operations\Services\IncidentService;
use App\Filament\Resources\Operations\Bookings\BookingResource;
use App\Filament\Resources\Operations\Incidents\Pages\ManageIncidents;
use App\Models\Incident;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class IncidentResource extends Resource
{
    protected static ?string $model = Incident::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedExclamationTriangle;

    protected static UnitEnum|string|null $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Incidents';

    protected static ?int $navigationSort = 6;

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(Permission::IncidentView->value) ?? false;
    }

    public static function canView(Model $record): bool
    {
        return auth()->user()?->can(Permission::IncidentView->value) ?? false;
    }

    public static function canCreate(): bool
    {
        return auth()->user()?->can(Permission::IncidentManage->value) ?? false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function schema(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Incident Information')->schema([
                TextInput::make('incident_number')
                    ->label('Incident Number')
                    ->disabled(),
                TextInput::make('type')
                    ->label('Type')
                    ->formatStateUsing(fn ($record) => $record->type?->label())
                    ->disabled(),
                TextInput::make('severity')
                    ->label('Severity')
                    ->formatStateUsing(fn ($record) => $record->severity?->label())
                    ->disabled(),
                TextInput::make('status')
                    ->label('Status')
                    ->formatStateUsing(fn ($record) => $record->status->label())
                    ->disabled(),
                TextInput::make('booking_reference')
                    ->label('Linked Booking')
                    ->formatStateUsing(fn ($record) => $record->booking ? $record->booking->reference : 'None')
                    ->disabled(),
                TextInput::make('vehicle_plate')
                    ->label('Vehicle')
                    ->formatStateUsing(fn ($record) => $record->vehicle ? $record->vehicle->license_plate : 'None')
                    ->disabled(),
                TextInput::make('driver_name')
                    ->label('Chauffeur')
                    ->formatStateUsing(fn ($record) => $record->driver ? $record->driver->full_name : 'None')
                    ->disabled(),
                TextInput::make('assigned_to')
                    ->label('Assigned Staff')
                    ->formatStateUsing(fn ($record) => $record->assignedToUser ? $record->assignedToUser->name : 'Unassigned')
                    ->disabled(),
                TextInput::make('occurred_at')
                    ->label('Occurred At (Colombo)')
                    ->formatStateUsing(fn ($record) => $record->occurred_at?->setTimezone('Asia/Colombo')->format('Y-m-d H:i'))
                    ->disabled(),
                TextInput::make('reported_at')
                    ->label('Reported At (Colombo)')
                    ->formatStateUsing(fn ($record) => $record->reported_at?->setTimezone('Asia/Colombo')->format('Y-m-d H:i'))
                    ->disabled(),
                Textarea::make('description')
                    ->label('Factual Incident Description')
                    ->disabled()
                    ->columnSpanFull(),
            ])->columns(2),

            Section::make('Police & Insurance Details')->schema([
                TextInput::make('police_report_filed')
                    ->label('Police Report Filed')
                    ->formatStateUsing(fn ($record) => $record->police_report_filed ? 'Yes' : 'No')
                    ->disabled(),
                TextInput::make('police_station')
                    ->label('Police Station')
                    ->disabled(),
                TextInput::make('police_report_number')
                    ->label('Police Report #')
                    ->disabled(),
                TextInput::make('insurance_claim_filed')
                    ->label('Insurance Claim Filed')
                    ->formatStateUsing(fn ($record) => $record->insurance_claim_filed ? 'Yes' : 'No')
                    ->disabled(),
                TextInput::make('insurance_claim_reference')
                    ->label('Claim Reference #')
                    ->disabled(),
                TextInput::make('financial_impact')
                    ->label('Total Financial Impact')
                    ->formatStateUsing(fn ($record) => sprintf('%s %.2f', $record->currency, $record->total_financial_impact_minor_units / 100))
                    ->disabled(),
            ])->columns(2),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with([
                'booking',
                'vehicle',
                'driver',
                'assignedToUser',
            ])->withCount(['actions', 'financialEntries', 'attachments']))
            ->columns([
                TextColumn::make('incident_number')
                    ->label('Incident #')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('booking.reference')
                    ->label('Booking Ref')
                    ->searchable()
                    ->sortable()
                    ->url(fn ($record) => $record->booking ? BookingResource::getUrl('view', ['record' => $record->booking]) : null),

                TextColumn::make('vehicle.license_plate')
                    ->label('Vehicle')
                    ->placeholder('N/A'),

                TextColumn::make('type')
                    ->label('Type')
                    ->badge(),

                TextColumn::make('severity')
                    ->label('Severity')
                    ->badge()
                    ->color(fn (IncidentSeverity $state) => match ($state) {
                        IncidentSeverity::Minor => 'gray',
                        IncidentSeverity::Moderate => 'info',
                        IncidentSeverity::Severe => 'warning',
                        IncidentSeverity::Critical => 'danger',
                    }),

                TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->color(fn (IncidentStatus $state) => match ($state) {
                        IncidentStatus::Reported => 'gray',
                        IncidentStatus::Investigating => 'info',
                        IncidentStatus::ActionRequired => 'warning',
                        IncidentStatus::Resolved => 'success',
                        IncidentStatus::Closed => 'gray',
                    }),

                TextColumn::make('total_financial_impact_minor_units')
                    ->label('Financial Impact')
                    ->formatStateUsing(fn ($state, $record) => sprintf('%s %.2f', $record->currency, $state / 100))
                    ->sortable(),

                TextColumn::make('occurred_at')
                    ->label('Occurred (Colombo)')
                    ->dateTime('M j, Y H:i', 'Asia/Colombo')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('severity')
                    ->options([
                        'minor' => 'Minor',
                        'moderate' => 'Moderate',
                        'severe' => 'Severe',
                        'critical' => 'Critical',
                    ]),
                SelectFilter::make('status')
                    ->options([
                        'reported' => 'Reported',
                        'investigating' => 'Investigating',
                        'action_required' => 'Action Required',
                        'resolved' => 'Resolved',
                        'closed' => 'Closed',
                    ]),
                SelectFilter::make('type')
                    ->options([
                        'accident' => 'Accident',
                        'breakdown' => 'Breakdown',
                        'damage' => 'Damage',
                        'theft' => 'Theft',
                        'traffic_violation' => 'Traffic Violation',
                        'customer_dispute' => 'Customer Dispute',
                        'service_delay' => 'Service Delay',
                    ]),
            ])
            ->actions([
                ViewAction::make(),

                // Update Status Action
                Action::make('updateStatus')
                    ->label('Status')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('warning')
                    ->visible(fn () => auth()->user()?->can(Permission::IncidentManage->value))
                    ->form([
                        Select::make('new_status')
                            ->label('New Status')
                            ->options([
                                'reported' => 'Reported (Under Assessment)',
                                'investigating' => 'Active Investigation',
                                'action_required' => 'Action Required',
                                'resolved' => 'Resolved',
                                'closed' => 'Closed',
                            ])
                            ->default(fn (Incident $record) => $record->status->value)
                            ->required(),

                        Textarea::make('reason')
                            ->label('Status Change Reason & Notes')
                            ->required(),
                    ])
                    ->action(function (Incident $record, array $data, IncidentService $incidentService) {
                        try {
                            $incidentService->updateStatus(
                                incident: $record,
                                staff: auth()->user(),
                                newStatus: IncidentStatus::from((string) $data['new_status']),
                                reason: (string) $data['reason']
                            );

                            Notification::make()
                                ->title('Incident Status Updated')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Status Update Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                // Record Financial Entry Action
                Action::make('recordFinance')
                    ->label('Add Cost')
                    ->icon(Heroicon::OutlinedCurrencyDollar)
                    ->color('success')
                    ->visible(fn () => auth()->user()?->can(Permission::IncidentManage->value))
                    ->form([
                        Select::make('entry_type')
                            ->label('Financial Movement Type')
                            ->options([
                                'customer_charge' => 'Direct Customer Surcharge',
                                'customer_deduction' => 'Security Deposit Deduction',
                                'repair_cost' => 'Workshop Repair Cost',
                                'third_party_recovery' => 'Third-Party Recovery',
                                'insurance_payout' => 'Insurance Claim Settlement',
                            ])
                            ->required(),

                        TextInput::make('amount_usd')
                            ->label('Amount (USD)')
                            ->numeric()
                            ->required(),

                        TextInput::make('description')
                            ->label('Description & Payee / Invoice Reference')
                            ->required(),
                    ])
                    ->action(function (Incident $record, array $data, IncidentService $incidentService) {
                        try {
                            $amountMinorUnits = (int) round(((float) $data['amount_usd']) * 100);

                            $incidentService->recordFinancialEntry(
                                incident: $record,
                                staff: auth()->user(),
                                type: IncidentFinancialEntryType::from((string) $data['entry_type']),
                                description: (string) $data['description'],
                                amountMinorUnits: $amountMinorUnits,
                                currency: 'USD'
                            );

                            Notification::make()
                                ->title('Financial Impact Recorded')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Recording Cost Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageIncidents::route('/'),
        ];
    }
}

