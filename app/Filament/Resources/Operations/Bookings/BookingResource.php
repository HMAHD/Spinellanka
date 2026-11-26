<?php

declare(strict_types=1);

namespace App\Filament\Resources\Operations\Bookings;

use App\Domain\Audit\Services\AuditService;
use App\Domain\Booking\DTOs\CreateProposalData;
use App\Domain\Booking\Enums\BookingSource;
use App\Domain\Booking\Enums\BookingStatus;
use App\Domain\Booking\Enums\ConfirmationChannel;
use App\Domain\Booking\Enums\ProposalPaymentChoice;
use App\Domain\Booking\Services\BookingLifecycleService;
use App\Domain\Booking\Services\ProposalService;
use App\Domain\Booking\Services\TokenRotationService;
use App\Domain\Booking\ServiceType;
use App\Domain\Catalog\Enums\DriverStatus;
use App\Domain\Catalog\Enums\VehicleStatus;
use App\Domain\Identity\Permission;
use App\Domain\Operations\Services\DriverAssignmentService;
use App\Domain\Payment\DTOs\OfflinePaymentData;
use App\Domain\Payment\DTOs\RefundData;
use App\Domain\Payment\Enums\PaymentMethod;
use App\Domain\Payment\Enums\PaymentType;
use App\Domain\Payment\Services\OfflinePaymentService;
use App\Domain\Payment\Services\RefundService;
use App\Domain\Pricing\DTOs\EstimateItemData;
use App\Domain\Pricing\Enums\PricingComponentType;
use App\Domain\Pricing\Enums\PricingUnit;
use App\Domain\Pricing\Services\ExchangeRateService;
use App\Domain\Pricing\ValueObjects\Money;
use App\Filament\Resources\Operations\Bookings\Pages\ManageBookings;
use App\Filament\Resources\Operations\Bookings\RelationManagers\FinancialDocumentsRelationManager;
use App\Filament\Resources\Operations\Bookings\RelationManagers\MessageDeliveriesRelationManager;
use App\Filament\Resources\Operations\Bookings\RelationManagers\PaymentTransactionsRelationManager;
use App\Filament\Resources\Operations\Bookings\RelationManagers\ProposalsRelationManager;
use App\Filament\Resources\Operations\MessageDeliveries\MessageDeliveryResource;
use App\Models\Booking;
use App\Models\Driver;
use App\Models\Vehicle;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class BookingResource extends Resource
{
    protected static ?string $model = Booking::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTicket;

    protected static UnitEnum|string|null $navigationGroup = 'Operations';

    protected static ?string $navigationLabel = 'Bookings';

    public static function canViewAny(): bool
    {
        return auth()->user()?->can(Permission::BookingView->value) ?? false;
    }

    public static function canView(Model $record): bool
    {
        return auth()->user()?->can(Permission::BookingView->value) ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
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
                Section::make('Booking Overview')
                    ->schema([
                        TextInput::make('reference')
                            ->disabled(),
                        TextInput::make('status')
                            ->formatStateUsing(fn ($state) => $state instanceof BookingStatus ? $state->label() : (string) $state)
                            ->disabled(),
                        TextInput::make('source')
                            ->formatStateUsing(fn ($state) => $state instanceof BookingSource ? $state->label() : (string) $state)
                            ->disabled(),
                        TextInput::make('created_at')
                            ->disabled(),
                    ])->columns(4),

                Section::make('Journey & Route Details')
                    ->visible(fn (?Booking $record) => $record?->service_type !== ServiceType::Rent)
                    ->schema([
                        TextInput::make('flight_or_ride_info')
                            ->label('Flight / Journey Identifier')
                            ->afterStateHydrated(fn (TextInput $component, ?Booking $record) => $component->state(
                                $record?->service_type === ServiceType::Ride
                                    ? ($record->rideDetail ? 'Ride: '.$record->rideDetail->origin_text.' → '.$record->rideDetail->destination_text : 'N/A')
                                    : ($record?->airportTransferDetail
                                        ? 'Flight: '.$record->airportTransferDetail->flight_number.' (CMB Transfer)'
                                        : ($record?->airportPickupDetail ? 'Flight: '.$record->airportPickupDetail->flight_number.' (CMB Pickup)' : 'N/A'))
                            ))
                            ->columnSpanFull()
                            ->disabled(),
                        TextInput::make('flight_timing')
                            ->label('Scheduled Timing')
                            ->afterStateHydrated(fn (TextInput $component, ?Booking $record) => $component->state(
                                $record?->service_type === ServiceType::Ride
                                    ? ($record->rideDetail ? 'Pickup: '.$record->rideDetail->pickup_time?->format('Y-m-d H:i').' (Asia/Colombo)' : 'N/A')
                                    : ($record?->airportTransferDetail
                                        ? 'Departs: '.$record->airportTransferDetail->departure_time?->format('Y-m-d H:i').' (Pickup: '.$record->airportTransferDetail->chosen_pickup_time?->format('H:i').')'
                                        : ($record?->airportPickupDetail ? 'Arrives: '.$record->airportPickupDetail->arrival_time?->format('Y-m-d H:i').' (Asia/Colombo)' : 'N/A'))
                            ))
                            ->disabled(),
                        TextInput::make('stops_or_safety')
                            ->label('En-Route Details')
                            ->afterStateHydrated(fn (TextInput $component, ?Booking $record) => $component->state(
                                $record?->service_type === ServiceType::Ride
                                    ? ($record->rideDetail ? "{$record->rideDetail->stops->count()} stops ({$record->rideDetail->stops->sum('planned_waiting_minutes')} min wait) | Return: ".($record->rideDetail->has_return ? $record->rideDetail->return_pickup_time?->format('Y-m-d H:i') : 'No') : 'None')
                                    : ($record?->airportTransferDetail?->is_unsafe_time
                                        ? '⚠️ Later than recommended (Warning Accepted)'
                                        : '✓ Within safe recommended margin')
                            ))
                            ->disabled(),
                        TextInput::make('passengers_luggage')
                            ->label('Party Size')
                            ->afterStateHydrated(fn (TextInput $component, ?Booking $record) => $component->state(
                                $record?->rideDetail
                                    ? "{$record->rideDetail->passenger_count} Pass / {$record->rideDetail->luggage_count} Bags"
                                    : ($record?->airportTransferDetail
                                        ? "{$record->airportTransferDetail->passenger_count} Pass / {$record->airportTransferDetail->luggage_count} Bags"
                                        : ($record?->airportPickupDetail ? "{$record->airportPickupDetail->passenger_count} Pass / {$record->airportPickupDetail->luggage_count} Bags" : 'N/A'))
                            ))
                            ->disabled(),
                    ])->columns(2),

                Section::make('Self-Drive Rental & Handover Details')
                    ->visible(fn (?Booking $record) => $record?->service_type === ServiceType::Rent)
                    ->schema([
                        TextInput::make('rent_pickup')
                            ->label('Pickup Handover (Colombo)')
                            ->afterStateHydrated(fn (TextInput $component, ?Booking $record) => $component->state(
                                $record?->rentDetail ? $record->rentDetail->pickup_datetime?->format('Y-m-d H:i').' ('.ucfirst($record->rentDetail->pickup_location_type).')' : 'N/A'
                            ))
                            ->disabled(),
                        TextInput::make('rent_return')
                            ->label('Return Collection (Colombo)')
                            ->afterStateHydrated(fn (TextInput $component, ?Booking $record) => $component->state(
                                $record?->rentDetail ? $record->rentDetail->return_datetime?->format('Y-m-d H:i').' ('.ucfirst($record->rentDetail->return_location_type).')' : 'N/A'
                            ))
                            ->disabled(),
                        TextInput::make('rent_locations')
                            ->label('Pickup & Return Places')
                            ->afterStateHydrated(fn (TextInput $component, ?Booking $record) => $component->state(
                                $record?->rentDetail ? 'Pickup: '.$record->rentDetail->pickup_location_text.' | Return: '.$record->rentDetail->return_location_text : 'N/A'
                            ))
                            ->columnSpanFull()
                            ->disabled(),
                        TextInput::make('rent_duration')
                            ->label('Rental Duration & Allowance')
                            ->afterStateHydrated(fn (TextInput $component, ?Booking $record) => $component->state(
                                $record?->rentDetail ? "{$record->rentDetail->duration_days} Days (".($record->rentDetail->extra_hours > 0 ? "+{$record->rentDetail->extra_hours}h " : '')."{$record->rentDetail->included_distance_km} km cumulative)" : 'N/A'
                            ))
                            ->disabled(),
                        TextInput::make('driver_eligibility')
                            ->label('Lead Driver Verification')
                            ->afterStateHydrated(fn (TextInput $component, ?Booking $record) => $component->state(
                                $record?->rentDetail ? "Age {$record->rentDetail->driver_age} • {$record->rentDetail->driver_licence_country} ({$record->rentDetail->driver_licence_years} yrs held) • IDP: ".($record->rentDetail->has_idp ? 'Yes' : 'No') : 'N/A'
                            ))
                            ->disabled(),
                        TextInput::make('additional_driver')
                            ->label('Additional Driver')
                            ->afterStateHydrated(fn (TextInput $component, ?Booking $record) => $component->state(
                                $record?->rentDetail?->has_additional_driver ? "{$record->rentDetail->additional_driver_name} (Age: {$record->rentDetail->additional_driver_age})" : 'None registered'
                            ))
                            ->disabled(),
                        TextInput::make('deposit_excess')
                            ->label('Guarantees (Deposit & Excess Cap)')
                            ->afterStateHydrated(fn (TextInput $component, ?Booking $record) => $component->state(
                                $record?->rentDetail ? 'Deposit: $'.number_format($record->rentDetail->security_deposit_minor_units / 100, 2).' USD | Excess: $'.number_format($record->rentDetail->insurance_excess_minor_units / 100, 2).' USD' : 'N/A'
                            ))
                            ->disabled(),
                        TextInput::make('handover_doc_status')
                            ->label('Document Inspection Status')
                            ->afterStateHydrated(fn (TextInput $component, ?Booking $record) => $component->state(
                                $record?->rentDetail?->original_documents_inspected
                                    ? "✓ Verified by Staff on {$record->rentDetail->original_documents_inspected_at?->format('d M Y H:i')}".($record->rentDetail->original_documents_inspection_notes ? " — {$record->rentDetail->original_documents_inspection_notes}" : '')
                                    : 'Pending physical in-person inspection of original passport, licence, and IDP at vehicle collection'
                            ))
                            ->columnSpanFull()
                            ->disabled(),
                    ])->columns(2),

                Section::make('Vehicle Allocation & Financials')
                    ->schema([
                        TextInput::make('vehicle_info')
                            ->label('Allocated Vehicle')
                            ->afterStateHydrated(fn (TextInput $component, ?Booking $record) => $component->state("{$record?->vehicle?->make} {$record?->vehicle?->model} ({$record?->vehicle?->license_plate})"))
                            ->disabled(),
                        TextInput::make('total_amount')
                            ->label('Total Estimated Amount')
                            ->afterStateHydrated(fn (TextInput $component, ?Booking $record) => $component->state($record?->total_amount_minor_units ? 'USD $'.number_format($record->total_amount_minor_units / 100, 2) : 'Custom Quote'))
                            ->disabled(),
                        TextInput::make('approx_lkr')
                            ->label('Approximate LKR Basis')
                            ->afterStateHydrated(fn (TextInput $component, ?Booking $record) => $component->state($record?->approximate_lkr_amount_minor_units ? 'LKR '.number_format($record->approximate_lkr_amount_minor_units / 100, 0) : 'N/A'))
                            ->disabled(),
                    ])->columns(3),

                Section::make('Manual Confirmation Record')
                    ->schema([
                        TextInput::make('confirmation_channel')
                            ->formatStateUsing(fn ($state) => $state instanceof ConfirmationChannel ? $state->label() : (string) $state)
                            ->disabled(),
                        TextInput::make('confirmed_at')
                            ->disabled(),
                        Textarea::make('confirmation_notes')
                            ->columnSpanFull()
                            ->disabled(),
                    ])
                    ->visible(fn (?Booking $record) => $record?->status === BookingStatus::Confirmed)
                    ->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['vehicle', 'airportPickupDetail', 'airportTransferDetail', 'rideDetail', 'rentDetail', 'booker', 'leadTraveler']))
            ->columns([
                TextColumn::make('reference')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->copyable(),

                TextColumn::make('service_type')
                    ->badge()
                    ->formatStateUsing(fn (ServiceType $state): string => $state->label()),

                TextColumn::make('status')
                    ->badge()
                    ->color(fn (BookingStatus $state): string => match ($state) {
                        BookingStatus::Submitted => 'warning',
                        BookingStatus::UnderReview => 'info',
                        BookingStatus::ProposalSent => 'primary',
                        BookingStatus::AwaitingPayment => 'warning',
                        BookingStatus::Confirmed, BookingStatus::Completed => 'success',
                        BookingStatus::Declined => 'danger',
                        BookingStatus::Cancelled, BookingStatus::Expired => 'gray',
                    })
                    ->formatStateUsing(fn (BookingStatus $state): string => $state->label()),

                TextColumn::make('flight')
                    ->label('Flight / Service')
                    ->getStateUsing(fn (Booking $record): string => match ($record->service_type) {
                        ServiceType::AirportTransfer => $record->airportTransferDetail->flight_number ?? 'N/A',
                        ServiceType::AirportPickup => $record->airportPickupDetail->flight_number ?? 'N/A',
                        ServiceType::Ride => 'Chauffeured Ride',
                        ServiceType::Rent => 'Self-Drive',
                    })
                    ->searchable(),

                TextColumn::make('service_time')
                    ->label('Timing')
                    ->getStateUsing(fn (Booking $record): string => match ($record->service_type) {
                        ServiceType::Rent => $record->rentDetail ? $record->rentDetail->pickup_datetime->format('d M H:i')." ({$record->rentDetail->duration_days}d)" : 'N/A',
                        ServiceType::Ride => $record->rideDetail ? $record->rideDetail->pickup_time->format('d M H:i').' (Pickup)' : 'N/A',
                        ServiceType::AirportTransfer => $record->airportTransferDetail ? ($record->airportTransferDetail->is_unsafe_time ? '⚠️ ' : '').$record->airportTransferDetail->chosen_pickup_time->format('d M H:i').' (Pickup)' : 'N/A',
                        ServiceType::AirportPickup => $record->airportPickupDetail && $record->airportPickupDetail->arrival_time ? $record->airportPickupDetail->arrival_time->format('d M H:i').' (Arrival)' : 'N/A',
                    })
                    ->sortable(),

                TextColumn::make('route')
                    ->label('Route / Location')
                    ->getStateUsing(fn (Booking $record): string => match ($record->service_type) {
                        ServiceType::Rent => $record->rentDetail ? $record->rentDetail->pickup_location_text.' → '.$record->rentDetail->return_location_text : 'N/A',
                        ServiceType::Ride => $record->rideDetail ? $record->rideDetail->origin_text.' → '.$record->rideDetail->destination_text : 'N/A',
                        ServiceType::AirportTransfer => $record->airportTransferDetail ? 'From: '.$record->airportTransferDetail->origin_text : 'N/A',
                        ServiceType::AirportPickup => $record->airportPickupDetail ? 'To: '.$record->airportPickupDetail->destination_text : 'N/A',
                    })
                    ->limit(25)
                    ->searchable(),

                TextColumn::make('vehicle.license_plate')
                    ->label('Vehicle')
                    ->formatStateUsing(fn ($state, Booking $record) => "{$record->vehicle?->make} ({$state})"),

                TextColumn::make('assignedDriver.public_name')
                    ->label('Chauffeur')
                    ->formatStateUsing(function ($state, Booking $record) {
                        if (! $state) {
                            return $record->service_type === ServiceType::Rent ? 'Self-Drive' : 'Unassigned';
                        }
                        $status = $record->currentDriverAssignment?->status?->label() ?? 'Assigned';

                        return "{$state} ({$status})";
                    })
                    ->badge()
                    ->color(fn ($state, Booking $record) => $state ? 'info' : ($record->service_type === ServiceType::Rent ? 'gray' : 'danger')),

                TextColumn::make('leadTraveler.phone')
                    ->label('Contact')
                    ->formatStateUsing(fn ($state, Booking $record) => $record->leadTraveler ? "{$record->leadTraveler->first_name} ({$state})" : 'N/A'),

                TextColumn::make('total_amount_minor_units')
                    ->label('Estimate')
                    ->formatStateUsing(fn (?int $state) => $state ? 'USD $'.number_format($state / 100, 2) : 'Custom Quote'),

                TextColumn::make('extension')
                    ->label('Extension')
                    ->badge()
                    ->color('warning')
                    ->getStateUsing(function (Booking $record): ?string {
                        if (! $record->rentDetail || empty($record->rentDetail->extension_state)) {
                            return null;
                        }
                        $latest = end($record->rentDetail->extension_state);

                        return isset($latest['extra_days']) ? "+{$latest['extra_days']}d" : 'Req';
                    })
                    ->placeholder('—'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->options(collect(BookingStatus::cases())->mapWithKeys(fn (BookingStatus $status) => [$status->value => $status->label()])),
            ])
            ->actions([
                ViewAction::make(),

                Action::make('inspect_documents')
                    ->label('Inspect Documents')
                    ->icon(Heroicon::OutlinedDocumentCheck)
                    ->color('success')
                    ->visible(fn (Booking $record): bool => $record->service_type === ServiceType::Rent
                        && ! ($record->rentDetail->original_documents_inspected ?? true)
                        && (auth()->user()?->can(Permission::BookingConfirm->value) ?? false))
                    ->modalHeading('Record Original Physical Document Inspection')
                    ->modalDescription('Verify that the lead driver has physically presented their original passport, national driving licence, and valid IDP/permit endorsement at vehicle collection.')
                    ->form([
                        Toggle::make('original_documents_verified')
                            ->label('Original physical documents inspected and verified')
                            ->required()
                            ->default(true),

                        Textarea::make('inspection_notes')
                            ->label('Inspection Notes / Observations')
                            ->placeholder('e.g. Inspected UK photo driving licence expiring 2030, 1968 IDP, and British passport with 30-day tourist visa.')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (Booking $record, array $data): void {
                        $record->rentDetail?->update([
                            'original_documents_inspected' => true,
                            'original_documents_inspected_at' => Carbon::now('Asia/Colombo'),
                            'original_documents_inspected_by' => auth()->id(),
                            'original_documents_inspection_notes' => trim($data['inspection_notes']),
                        ]);

                        app(AuditService::class)->record(
                            action: 'booking.rent_documents_inspected',
                            actor: auth()->user(),
                            summary: "Original physical documents inspected and verified for rent booking {$record->reference}",
                            metadata: [
                                'booking_id' => $record->id,
                                'reference' => $record->reference,
                                'notes' => $data['inspection_notes'],
                            ]
                        );

                        Notification::make()->title('Original documents inspection recorded successfully.')->success()->send();
                    }),

                Action::make('review')
                    ->label('Review')
                    ->icon(Heroicon::OutlinedEye)
                    ->color('info')
                    ->visible(fn (Booking $record): bool => $record->status === BookingStatus::Submitted && (auth()->user()?->can(Permission::BookingReview->value) ?? false))
                    ->requiresConfirmation()
                    ->action(function (Booking $record): void {
                        app(BookingLifecycleService::class)->markUnderReview($record, auth()->user());
                        Notification::make()->title('Booking marked under review.')->success()->send();
                    }),

                Action::make('confirm')
                    ->label('Confirm')
                    ->icon(Heroicon::OutlinedCheckCircle)
                    ->color('success')
                    ->visible(fn (Booking $record): bool => $record->canBeConfirmed() && (auth()->user()?->can(Permission::BookingConfirm->value) ?? false))
                    ->modalHeading('Confirm Airport Pickup Booking')
                    ->modalDescription('Verify direct customer communication outcome and schedule availability before confirming.')
                    ->form([
                        Select::make('confirmation_channel')
                            ->label('Customer Communication Channel')
                            ->options(collect(ConfirmationChannel::cases())->mapWithKeys(fn (ConfirmationChannel $c) => [$c->value => $c->label()]))
                            ->default(ConfirmationChannel::WhatsApp->value)
                            ->required(),

                        Textarea::make('confirmation_notes')
                            ->label('Communication Outcome Notes')
                            ->placeholder('e.g. Communicated with lead traveler on WhatsApp (+44...), verified flight arrival and meet & greet location.')
                            ->required()
                            ->rows(3),

                        Textarea::make('override_reason')
                            ->label('Conflict Override Reason (If schedule conflict exists)')
                            ->placeholder('Document approval justification for overriding any schedule conflict.')
                            ->visible(fn () => auth()->user()?->can(Permission::BookingOverrideConflict->value) ?? false)
                            ->rows(2),
                    ])
                    ->action(function (Booking $record, array $data): void {
                        $channel = ConfirmationChannel::from($data['confirmation_channel']);
                        app(BookingLifecycleService::class)->confirm(
                            booking: $record,
                            staff: auth()->user(),
                            channel: $channel,
                            notes: $data['confirmation_notes'],
                            overrideReason: $data['override_reason'] ?? null
                        );
                        Notification::make()->title("Booking {$record->reference} confirmed.")->success()->send();
                    }),

                Action::make('decline')
                    ->label('Decline')
                    ->icon(Heroicon::OutlinedXCircle)
                    ->color('danger')
                    ->visible(fn (Booking $record): bool => $record->canBeDeclined() && (auth()->user()?->can(Permission::BookingReview->value) ?? false))
                    ->modalHeading('Decline Booking Request')
                    ->form([
                        Textarea::make('decline_reason')
                            ->label('Decline Reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (Booking $record, array $data): void {
                        app(BookingLifecycleService::class)->decline($record, auth()->user(), $data['decline_reason']);
                        Notification::make()->title("Booking {$record->reference} declined.")->danger()->send();
                    }),

                Action::make('cancel')
                    ->label('Cancel')
                    ->icon(Heroicon::OutlinedNoSymbol)
                    ->color('gray')
                    ->visible(fn (Booking $record): bool => $record->canBeCancelled() && (auth()->user()?->can(Permission::BookingCancel->value) ?? false))
                    ->form([
                        Textarea::make('cancellation_reason')
                            ->label('Cancellation Reason')
                            ->required()
                            ->rows(3),
                    ])
                    ->action(function (Booking $record, array $data): void {
                        app(BookingLifecycleService::class)->cancel($record, $data['cancellation_reason'], auth()->user());
                        Notification::make()->title("Booking {$record->reference} cancelled.")->warning()->send();
                    }),

                Action::make('create_proposal')
                    ->label('Create Proposal')
                    ->icon(Heroicon::OutlinedDocumentPlus)
                    ->color('primary')
                    ->visible(fn (Booking $record): bool => $record->status === BookingStatus::UnderReview && (auth()->user()?->can(Permission::BookingReview->value) ?? false))
                    ->modalHeading('Create & Send Proposal (v1)')
                    ->modalDescription('Specify vehicle, pricing, and payment choices. A proposal hold schedule block will be created automatically.')
                    ->form([
                        Select::make('vehicle_id')
                            ->label('Fleet Vehicle')
                            ->options(fn () => Vehicle::where('status', VehicleStatus::Active)->pluck('license_plate', 'id')->map(function ($plate, $id) {
                                $v = Vehicle::find($id);

                                return $v ? "{$v->make} {$v->model} ({$plate})" : (string) $plate;
                            }))
                            ->default(fn (Booking $record) => $record->vehicle_id)
                            ->required(),

                        TextInput::make('total_amount')
                            ->label('Total Price (EUR / Base)')
                            ->numeric()
                            ->default(fn (Booking $record) => $record->total_amount_minor_units ? $record->total_amount_minor_units / 100 : 75.00)
                            ->required(),

                        Toggle::make('deposit_required')
                            ->label('Require Advance Security Deposit')
                            ->default(fn (Booking $record) => $record->service_type === ServiceType::Rent)
                            ->reactive(),

                        TextInput::make('deposit_amount')
                            ->label('Security Deposit Amount')
                            ->numeric()
                            ->default(fn (Booking $record) => $record->service_type === ServiceType::Rent ? 250.00 : 0.00)
                            ->visible(fn ($get) => (bool) $get('deposit_required')),

                        Select::make('payment_choice')
                            ->label('Permitted Guest Payment Choice')
                            ->options(collect(ProposalPaymentChoice::cases())->mapWithKeys(fn (ProposalPaymentChoice $c) => [$c->value => $c->label()]))
                            ->default(fn (Booking $record) => $record->service_type === ServiceType::Rent ? ProposalPaymentChoice::FullOrDeposit->value : ProposalPaymentChoice::FullOrPayLater->value)
                            ->required(),

                        Select::make('expires_in_hours')
                            ->label('Guest Response Deadline')
                            ->options([
                                '24' => '24 Hours',
                                '48' => '48 Hours (Recommended)',
                                '72' => '72 Hours',
                                '168' => '7 Days',
                            ])
                            ->default('48')
                            ->required(),

                        Textarea::make('staff_notes')
                            ->label('Notes to Guest (Visible on status page)')
                            ->placeholder('e.g. Special routing through Southern Expressway included. Chauffeur meets at arrival hall.')
                            ->rows(3),

                        Textarea::make('internal_notes')
                            ->label('Internal Dispatch Margin / Strategy Notes (Staff Only - Hidden from Guest)')
                            ->placeholder('Internal calculation basis, driver commission, or commercial notes.')
                            ->rows(2),
                    ])
                    ->action(function (Booking $record, array $data): void {
                        $vehicle = Vehicle::findOrFail((int) $data['vehicle_id']);
                        $startsAt = Carbon::now('Asia/Colombo')->addDays(2);
                        $endsAt = (clone $startsAt)->addHours(4);

                        if ($record->airportPickupDetail?->arrival_time) {
                            $startsAt = $record->airportPickupDetail->arrival_time;
                            $endsAt = (clone $startsAt)->addMinutes(120);
                        } elseif ($record->airportTransferDetail?->chosen_pickup_time) {
                            $startsAt = $record->airportTransferDetail->chosen_pickup_time;
                            $endsAt = (clone $startsAt)->addMinutes(150);
                        } elseif ($record->rideDetail?->pickup_time) {
                            $startsAt = $record->rideDetail->pickup_time;
                            $endsAt = $record->rideDetail->return_pickup_time ?? (clone $startsAt)->addHours(3);
                        } elseif ($record->rentDetail?->pickup_datetime) {
                            $startsAt = $record->rentDetail->pickup_datetime;
                            $endsAt = $record->rentDetail->return_datetime ?? (clone $startsAt)->addDays(1);
                        }

                        $totalMinor = (int) round(((float) $data['total_amount']) * 100);
                        $depositMinor = ! empty($data['deposit_required']) ? (int) round(((float) ($data['deposit_amount'] ?? 0)) * 100) : 0;
                        $approxLkrMoney = app(ExchangeRateService::class)->approximateLkr(Money::of($totalMinor, $record->currency ?: 'USD'));
                        $approxLkr = $approxLkrMoney !== null ? $approxLkrMoney->amountMinorUnits : (int) round($totalMinor * 308.5);

                        $proposalData = new CreateProposalData(
                            vehicleId: $vehicle->id,
                            serviceType: $record->service_type,
                            startsAt: $startsAt,
                            endsAt: $endsAt,
                            currency: $record->currency ?: 'EUR',
                            totalAmountMinorUnits: $totalMinor,
                            approximateLkrAmountMinorUnits: $approxLkr,
                            depositRequired: (bool) ($data['deposit_required'] ?? false),
                            depositAmountMinorUnits: $depositMinor,
                            paymentChoice: ProposalPaymentChoice::from($data['payment_choice']),
                            expiresAt: Carbon::now('Asia/Colombo')->addHours((int) $data['expires_in_hours']),
                            items: [
                                new EstimateItemData(
                                    componentType: PricingComponentType::Base,
                                    description: "Standard {$record->service_type->label()} Fare",
                                    unit: PricingUnit::Flat,
                                    quantity: 1.0,
                                    unitPrice: Money::of($totalMinor, $record->currency ?: 'EUR'),
                                    totalAmount: Money::of($totalMinor, $record->currency ?: 'EUR'),
                                    sortOrder: 1,
                                    isDeposit: false,
                                    isTax: false,
                                    isDiscount: false,
                                ),
                            ],
                            snapshotData: [
                                'service_type' => $record->service_type->value,
                                'vehicle_name' => "{$vehicle->make} {$vehicle->model}",
                                'service_details' => $record->airportPickupDetail?->toArray()
                                    ?? $record->airportTransferDetail?->toArray()
                                    ?? $record->rideDetail?->toArray()
                                    ?? $record->rentDetail?->toArray()
                                    ?? [],
                            ],
                            staffNotes: $data['staff_notes'] ?? null,
                            internalNotes: $data['internal_notes'] ?? null
                        );

                        $proposalService = app(ProposalService::class);
                        $draft = $proposalService->createDraft($record, $proposalData, auth()->user());
                        $sent = $proposalService->sendProposal($draft, auth()->user());

                        Notification::make()->title("Proposal {$sent->reference} created and dispatched.")->success()->send();
                    }),

                Action::make('revise_proposal')
                    ->label('Revise Proposal')
                    ->icon(Heroicon::OutlinedPencilSquare)
                    ->color('warning')
                    ->visible(fn (Booking $record): bool => ($record->status === BookingStatus::ProposalSent || $record->status === BookingStatus::Confirmed) && (auth()->user()?->can(Permission::BookingReview->value) ?? false))
                    ->modalHeading(fn (Booking $record) => $record->status === BookingStatus::Confirmed ? 'Revise Confirmed Travel Agreement' : 'Revise Proposal')
                    ->modalDescription(fn (Booking $record) => $record->status === BookingStatus::Confirmed
                        ? 'Under Pillar B, your confirmed travel agreement remains 100% active and protected until the traveler explicitly accepts this new version. Please confirm staff authorization.'
                        : 'Creates an incremented proposal version and releases previous temporary holds.')
                    ->form(function (Booking $record) {
                        $fields = [];

                        $currProp = $record->currentProposal;

                        if ($record->status === BookingStatus::Confirmed) {
                            $fields[] = TextInput::make('reauth_password')
                                ->label('Re-enter Staff Password')
                                ->password()
                                ->required()
                                ->rule('current_password:web')
                                ->helperText('Password re-authentication required when revising a confirmed travel agreement.');
                        }

                        $fields[] = Select::make('vehicle_id')
                            ->label('Fleet Vehicle')
                            ->options(fn () => Vehicle::where('status', VehicleStatus::Active)->pluck('license_plate', 'id')->map(function ($plate, $id) {
                                $v = Vehicle::find($id);

                                return $v ? "{$v->make} {$v->model} ({$plate})" : (string) $plate;
                            }))
                            ->default($currProp ? $currProp->vehicle_id : $record->vehicle_id)
                            ->required();

                        $fields[] = TextInput::make('total_amount')
                            ->label('Total Price (EUR / Base)')
                            ->numeric()
                            ->default($currProp ? $currProp->total_amount_minor_units / 100 : ($record->total_amount_minor_units ? $record->total_amount_minor_units / 100 : 75.00))
                            ->required();

                        $fields[] = Toggle::make('deposit_required')
                            ->label('Require Advance Security Deposit')
                            ->default($currProp ? $currProp->deposit_required : ($record->service_type === ServiceType::Rent))
                            ->reactive();

                        $fields[] = TextInput::make('deposit_amount')
                            ->label('Security Deposit Amount')
                            ->numeric()
                            ->default($currProp ? $currProp->deposit_amount_minor_units / 100 : 0.00)
                            ->visible(fn ($get) => (bool) $get('deposit_required'));

                        $fields[] = Select::make('payment_choice')
                            ->label('Permitted Guest Payment Choice')
                            ->options(collect(ProposalPaymentChoice::cases())->mapWithKeys(fn (ProposalPaymentChoice $c) => [$c->value => $c->label()]))
                            ->default($currProp ? $currProp->payment_choice->value : ProposalPaymentChoice::FullOrDeposit->value)
                            ->required();

                        $fields[] = Select::make('expires_in_hours')
                            ->label('Guest Response Deadline')
                            ->options([
                                '24' => '24 Hours',
                                '48' => '48 Hours',
                                '72' => '72 Hours',
                                '168' => '7 Days',
                            ])
                            ->default('48')
                            ->required();

                        $fields[] = Textarea::make('staff_notes')
                            ->label('Notes to Guest (Visible on status page)')
                            ->placeholder('Explanation of revision or quote change.')
                            ->rows(3);

                        $fields[] = Textarea::make('internal_notes')
                            ->label('Internal Dispatch Notes (Hidden from Guest)')
                            ->rows(2);

                        return $fields;
                    })
                    ->action(function (Booking $record, array $data): void {
                        $vehicle = Vehicle::findOrFail((int) $data['vehicle_id']);
                        $currProp = $record->currentProposal;
                        $startsAt = $currProp ? $currProp->starts_at : Carbon::now('Asia/Colombo')->addDays(2);
                        $endsAt = $currProp ? $currProp->ends_at : (clone $startsAt)->addHours(4);

                        $totalMinor = (int) round(((float) $data['total_amount']) * 100);
                        $depositMinor = ! empty($data['deposit_required']) ? (int) round(((float) ($data['deposit_amount'] ?? 0)) * 100) : 0;
                        $approxLkrMoney = app(ExchangeRateService::class)->approximateLkr(Money::of($totalMinor, $record->currency ?: 'USD'));
                        $approxLkr = $approxLkrMoney !== null ? $approxLkrMoney->amountMinorUnits : (int) round($totalMinor * 308.5);

                        $proposalData = new CreateProposalData(
                            vehicleId: $vehicle->id,
                            serviceType: $record->service_type,
                            startsAt: $startsAt,
                            endsAt: $endsAt,
                            currency: $record->currency ?: 'EUR',
                            totalAmountMinorUnits: $totalMinor,
                            approximateLkrAmountMinorUnits: $approxLkr,
                            depositRequired: (bool) ($data['deposit_required'] ?? false),
                            depositAmountMinorUnits: $depositMinor,
                            paymentChoice: ProposalPaymentChoice::from($data['payment_choice']),
                            expiresAt: Carbon::now('Asia/Colombo')->addHours((int) $data['expires_in_hours']),
                            items: [
                                new EstimateItemData(
                                    componentType: PricingComponentType::Base,
                                    description: "Amended {$record->service_type->label()} Fare",
                                    unit: PricingUnit::Flat,
                                    quantity: 1.0,
                                    unitPrice: Money::of($totalMinor, $record->currency ?: 'EUR'),
                                    totalAmount: Money::of($totalMinor, $record->currency ?: 'EUR'),
                                    sortOrder: 1,
                                    isDeposit: false,
                                    isTax: false,
                                    isDiscount: false,
                                ),
                            ],
                            snapshotData: [
                                'service_type' => $record->service_type->value,
                                'vehicle_name' => "{$vehicle->make} {$vehicle->model}",
                                'service_details' => $record->airportPickupDetail?->toArray()
                                    ?? $record->airportTransferDetail?->toArray()
                                    ?? $record->rideDetail?->toArray()
                                    ?? $record->rentDetail?->toArray()
                                    ?? [],
                            ],
                            staffNotes: $data['staff_notes'] ?? null,
                            internalNotes: $data['internal_notes'] ?? null
                        );

                        $revised = app(ProposalService::class)->reviseProposal($record, $proposalData, auth()->user());

                        Notification::make()->title("Proposal revised: version {$revised->reference} dispatched.")->success()->send();
                    }),

                Action::make('rotate_guest_link')
                    ->label('Rotate Guest Link')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('gray')
                    ->visible(fn () => auth()->user()?->can(Permission::BookingReview->value) ?? false)
                    ->form([
                        TextInput::make('reauth_password')
                            ->label('Re-enter Staff Password')
                            ->password()
                            ->required()
                            ->rule('current_password:web')
                            ->helperText('Security policy mandates password re-authentication before regenerating private links.'),
                    ])
                    ->modalHeading('Rotate Private Guest Access Link')
                    ->modalDescription('This will immediately revoke all previously issued links for this booking and generate a fresh 256-bit secure access token.')
                    ->action(function (Booking $record): void {
                        $token = app(TokenRotationService::class)->rotateToken($record, 'guest_access', auth()->user());
                        $url = route('booking.status', ['reference' => $record->reference, 'token' => $token]);

                        Notification::make()
                            ->title('Guest access link rotated successfully.')
                            ->body("New link: {$url}")
                            ->success()
                            ->persistent()
                            ->send();
                    }),

                Action::make('record_offline_payment')
                    ->label('Record Payment')
                    ->icon(Heroicon::OutlinedBanknotes)
                    ->color('success')
                    ->visible(fn (Booking $record): bool => (auth()->user()?->can(Permission::PaymentRecordOffline->value) ?? false)
                        && in_array($record->status, [BookingStatus::AwaitingPayment, BookingStatus::ProposalSent, BookingStatus::Confirmed], true))
                    ->modalHeading('Record Offline Payment')
                    ->modalDescription('Record cash, wire transfer, or card payment received offline. This confirms awaiting payment bookings and issues an official receipt.')
                    ->form([
                        TextInput::make('amount')
                            ->label('Amount Received')
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->prefix(fn (Booking $record): string => $record->currency ?: 'USD'),
                        Select::make('payment_method')
                            ->label('Payment Method')
                            ->options([
                                PaymentMethod::Cash->value => PaymentMethod::Cash->label(),
                                PaymentMethod::BankTransfer->value => PaymentMethod::BankTransfer->label(),
                            ])
                            ->required()
                            ->default(PaymentMethod::Cash->value),
                        TextInput::make('reference_note')
                            ->label('Reference / Slip Note')
                            ->maxLength(255)
                            ->placeholder('e.g. Bank slip number, cash receipt reference'),
                        Textarea::make('notes')
                            ->label('Internal Notes')
                            ->maxLength(1000),
                    ])
                    ->action(function (Booking $record, array $data): void {
                        $amountMinor = (int) round(((float) $data['amount']) * 100);
                        $offlineData = new OfflinePaymentData(
                            amountMinorUnits: $amountMinor,
                            currency: $record->currency ?: 'USD',
                            paymentMethod: PaymentMethod::from($data['payment_method']),
                            paymentType: PaymentType::Full,
                            notes: $data['notes'] ?? '',
                            externalReference: $data['reference_note'] ?? null,
                        );

                        try {
                            $transaction = app(OfflinePaymentService::class)->recordOfflinePayment($record, auth()->user(), $offlineData);
                            Notification::make()
                                ->title('Offline payment recorded successfully.')
                                ->body("Recorded {$transaction->currency} ".number_format($transaction->amount_minor_units / 100, 2)." via {$transaction->payment_method->label()}.")
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Failed to record offline payment')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('issue_refund')
                    ->label('Authorize Refund')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('danger')
                    ->visible(fn (Booking $record): bool => (auth()->user()?->can(Permission::PaymentRefund->value) ?? false)
                        && $record->totalSettledAmount() > 0)
                    ->modalHeading('Authorize Compensating Refund')
                    ->modalDescription(fn (Booking $record): string => "Currently settled balance: {$record->currency} ".number_format($record->totalSettledAmount() / 100, 2).'. Authorizing a refund creates an immutable compensating ledger entry. Password re-authentication is mandatory.')
                    ->form([
                        TextInput::make('amount')
                            ->label('Refund Amount')
                            ->numeric()
                            ->required()
                            ->minValue(0.01)
                            ->prefix(fn (Booking $record): string => $record->currency ?: 'USD'),
                        Textarea::make('reason')
                            ->label('Reason for Refund')
                            ->required()
                            ->minLength(5)
                            ->maxLength(1000),
                        TextInput::make('reauth_password')
                            ->label('Re-enter Staff Password')
                            ->password()
                            ->required()
                            ->rule('current_password:web')
                            ->helperText('Financial governance mandates password re-authentication before authorizing refunds.'),
                    ])
                    ->action(function (Booking $record, array $data): void {
                        $amountMinor = (int) round(((float) $data['amount']) * 100);
                        $refundData = new RefundData(
                            amountMinorUnits: $amountMinor,
                            reason: $data['reason']
                        );

                        try {
                            $refund = app(RefundService::class)->issueRefund($record, auth()->user(), $refundData);
                            Notification::make()
                                ->title('Compensating refund authorized.')
                                ->body("Refunded {$refund->currency} ".number_format($refund->amount_minor_units / 100, 2).' and updated ledger.')
                                ->success()
                                ->send();
                        } catch (\Throwable $e) {
                            Notification::make()
                                ->title('Failed to authorize refund')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('deliveries')
                    ->label('Deliveries')
                    ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                    ->color('gray')
                    ->url(fn (Booking $record): string => MessageDeliveryResource::getUrl('index', [
                        'tableSearch' => $record->reference,
                    ])),

                Action::make('assign_driver')
                    ->label('Assign Driver')
                    ->icon(Heroicon::OutlinedUserPlus)
                    ->color('info')
                    ->visible(fn (Booking $record): bool => in_array($record->service_type, [ServiceType::AirportPickup, ServiceType::AirportTransfer, ServiceType::Ride], true)
                        && $record->status === BookingStatus::Confirmed
                        && (auth()->user()?->can(Permission::BookingConfirm->value) ?? false))
                    ->modalHeading('Assign Dedicated Fleet Driver')
                    ->modalDescription(function (Booking $record): string {
                        $serviceStart = $record->getServiceStartTime() ?? Carbon::now('Asia/Colombo');
                        $tiedDriver = app(DriverAssignmentService::class)->resolveTiedDriver($record->vehicle, $serviceStart);
                        $tiedText = $tiedDriver ? "Permanent tied driver: {$tiedDriver->public_name}." : 'No permanent tied driver for this vehicle.';

                        return "Allocate an active verified chauffeur for booking {$record->reference}. {$tiedText}";
                    })
                    ->form(function (Booking $record): array {
                        $serviceStart = $record->getServiceStartTime() ?? Carbon::now('Asia/Colombo');
                        $tiedDriver = app(DriverAssignmentService::class)->resolveTiedDriver($record->vehicle, $serviceStart);
                        $drivers = Driver::where('status', DriverStatus::Active->value)->orderBy('public_name')->get();

                        return [
                            Select::make('driver_id')
                                ->label('Select Fleet Driver')
                                ->options($drivers->pluck('public_name', 'id'))
                                ->default($tiedDriver?->id)
                                ->required()
                                ->live()
                                ->helperText($tiedDriver ? "Recommended tied driver: {$tiedDriver->public_name}" : 'Select an active verified driver'),

                            Textarea::make('replacement_reason')
                                ->label('Reason for Chauffeur Replacement (Required Governance)')
                                ->placeholder('e.g. Tied driver on approved annual leave, or language preference requested by guest.')
                                ->visible(fn (Get $get) => $tiedDriver && ((int) $get('driver_id')) !== $tiedDriver->id)
                                ->required(fn (Get $get) => $tiedDriver && ((int) $get('driver_id')) !== $tiedDriver->id)
                                ->rows(2),
                        ];
                    })
                    ->action(function (Booking $record, array $data): void {
                        $driver = Driver::findOrFail((int) $data['driver_id']);
                        $serviceStart = $record->getServiceStartTime() ?? Carbon::now('Asia/Colombo');
                        $tiedDriver = app(DriverAssignmentService::class)->resolveTiedDriver($record->vehicle, $serviceStart);
                        $isReplacement = $tiedDriver && $driver->id !== $tiedDriver->id;

                        try {
                            $result = app(DriverAssignmentService::class)->assignDriver(
                                booking: $record,
                                driver: $driver,
                                staff: auth()->user(),
                                isReplacement: $isReplacement,
                                replacementReason: $isReplacement ? ($data['replacement_reason'] ?? null) : null,
                            );

                            $tripUrl = route('driver.trip.show', ['token' => $result['plainToken']]);

                            Notification::make()
                                ->title("Driver {$driver->public_name} assigned to {$record->reference}")
                                ->body("Secure Trip Link generated: {$tripUrl}")
                                ->success()
                                ->persistent()
                                ->send();
                        } catch (\InvalidArgumentException $e) {
                            Notification::make()
                                ->title('Assignment Failed')
                                ->body($e->getMessage())
                                ->danger()
                                ->send();
                        }
                    }),

                Action::make('rotate_driver_link')
                    ->label('Rotate Trip Link')
                    ->icon(Heroicon::OutlinedArrowPath)
                    ->color('warning')
                    ->visible(fn (Booking $record): bool => $record->assigned_driver_id !== null
                        && $record->status === BookingStatus::Confirmed
                        && (auth()->user()?->can(Permission::BookingConfirm->value) ?? false))
                    ->modalHeading('Rotate Driver Trip Link')
                    ->modalDescription('Revokes the active driver trip link and issues a fresh secure link. The previous link will immediately stop functioning.')
                    ->form([
                        TextInput::make('reason')
                            ->label('Reason for Link Rotation')
                            ->required()
                            ->placeholder('e.g. Chauffeur replaced smartphone or requested fresh link'),
                    ])
                    ->action(function (Booking $record, array $data): void {
                        $newPlainToken = app(DriverAssignmentService::class)->rotateTripToken($record, auth()->user(), $data['reason']);
                        $newUrl = route('driver.trip.show', ['token' => $newPlainToken]);

                        Notification::make()
                            ->title('Driver Trip Link Rotated')
                            ->body("New Secure Trip Link: {$newUrl}")
                            ->success()
                            ->persistent()
                            ->send();
                    }),

                Action::make('revoke_driver_access')
                    ->label('Revoke Driver Access')
                    ->icon(Heroicon::OutlinedXMark)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Booking $record): bool => $record->assigned_driver_id !== null
                        && (auth()->user()?->can(Permission::BookingConfirm->value) ?? false))
                    ->modalHeading('Revoke Driver Trip Access')
                    ->modalDescription('Immediately revokes all active trip tokens and removes the assigned chauffeur from this booking.')
                    ->form([
                        TextInput::make('reason')
                            ->label('Reason for Revocation')
                            ->required()
                            ->placeholder('e.g. Driver emergency or scheduling reallocation'),
                    ])
                    ->action(function (Booking $record, array $data): void {
                        app(DriverAssignmentService::class)->revokeDriverAccess($record, auth()->user(), $data['reason']);

                        Notification::make()
                            ->title('Driver Access Revoked')
                            ->body("Driver removed from booking {$record->reference}.")
                            ->warning()
                            ->send();
                    }),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            ProposalsRelationManager::class,
            PaymentTransactionsRelationManager::class,
            FinancialDocumentsRelationManager::class,
            MessageDeliveriesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageBookings::route('/'),
        ];
    }
}
