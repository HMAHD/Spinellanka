<?php

declare(strict_types=1);

namespace App\Filament\Resources\Operations\Bookings\Pages;

use App\Domain\Booking\DTOs\AirportPickupCriteria;
use App\Domain\Booking\DTOs\AirportTransferCriteria;
use App\Domain\Booking\DTOs\RentCriteria;
use App\Domain\Booking\Enums\BookingSource;
use App\Domain\Booking\Enums\ConfirmationChannel;
use App\Domain\Booking\Services\AirportTransferRecommendationCalculator;
use App\Domain\Booking\Services\ManualBookingService;
use App\Domain\Booking\Services\RentDurationCalculator;
use App\Domain\Catalog\Enums\VehicleStatus;
use App\Domain\Identity\Permission;
use App\Filament\Resources\Operations\Bookings\BookingResource;
use App\Models\RentPolicy;
use App\Models\Vehicle;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ManageRecords;

class ManageBookings extends ManageRecords
{
    protected static string $resource = BookingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('manual_booking')
                ->label('New Manual Booking')
                ->icon('heroicon-o-plus')
                ->visible(fn (): bool => auth()->user()?->can(Permission::BookingConfirm->value) ?? false)
                ->modalHeading('Create Manual Airport Pickup')
                ->modalDescription('Record a direct phone, WhatsApp, or walk-in airport pickup reservation.')
                ->form([
                    Select::make('source')
                        ->label('Intake Source')
                        ->options([
                            BookingSource::ManualPhone->value => BookingSource::ManualPhone->label(),
                            BookingSource::ManualWhatsApp->value => BookingSource::ManualWhatsApp->label(),
                            BookingSource::ManualWalkin->value => BookingSource::ManualWalkin->label(),
                        ])
                        ->default(BookingSource::ManualPhone->value)
                        ->required(),

                    Select::make('vehicle_id')
                        ->label('Assigned Vehicle')
                        ->options(
                            Vehicle::where('eligible_for_pickup', true)
                                ->where('status', VehicleStatus::Active)
                                ->get()
                                ->mapWithKeys(fn (Vehicle $v) => [$v->id => "{$v->make} {$v->model} ({$v->license_plate}) - {$v->passenger_capacity} Pass"])
                        )
                        ->required(),

                    TextInput::make('flight_number')
                        ->label('Flight Number')
                        ->placeholder('e.g. UL504')
                        ->required(),

                    DateTimePicker::make('arrival_time')
                        ->label('Arrival Date & Time (Asia/Colombo)')
                        ->default(now('Asia/Colombo')->addDay())
                        ->required(),

                    TextInput::make('destination_text')
                        ->label('Drop-off Destination')
                        ->placeholder('e.g. Galle Face Hotel, Colombo')
                        ->required(),

                    TextInput::make('passenger_count')
                        ->label('Passengers')
                        ->numeric()
                        ->default(2)
                        ->required(),

                    TextInput::make('luggage_count')
                        ->label('Luggage Bags')
                        ->numeric()
                        ->default(2)
                        ->required(),

                    TextInput::make('booker_first_name')
                        ->label('Customer First Name')
                        ->required(),

                    TextInput::make('booker_last_name')
                        ->label('Customer Last Name')
                        ->required(),

                    TextInput::make('booker_phone')
                        ->label('Customer Phone / WhatsApp (with country code)')
                        ->placeholder('+44 ...')
                        ->required(),

                    Select::make('confirmation_channel')
                        ->label('Confirmation Channel')
                        ->options(collect(ConfirmationChannel::cases())->mapWithKeys(fn (ConfirmationChannel $c) => [$c->value => $c->label()]))
                        ->default(ConfirmationChannel::Phone->value)
                        ->required(),

                    Textarea::make('confirmation_notes')
                        ->label('Intake & Communication Notes')
                        ->required()
                        ->rows(2),

                    Textarea::make('override_reason')
                        ->label('Conflict Override Reason (If schedule conflict exists)')
                        ->visible(fn () => auth()->user()?->can(Permission::BookingOverrideConflict->value) ?? false)
                        ->rows(2),
                ])
                ->action(function (array $data): void {
                    $arrivalTime = Carbon::parse($data['arrival_time'])->setTimezone('Asia/Colombo');
                    $criteria = new AirportPickupCriteria(
                        airport: 'CMB',
                        flightNumber: strtoupper(trim($data['flight_number'])),
                        arrivalTime: $arrivalTime,
                        destinationText: trim($data['destination_text']),
                        passengerCount: (int) $data['passenger_count'],
                        luggageCount: (int) $data['luggage_count'],
                    );

                    $manualData = [
                        'source' => BookingSource::from($data['source']),
                        'criteria' => $criteria,
                        'vehicle_id' => (int) $data['vehicle_id'],
                        'estimate_reference' => null,
                        'booker_first_name' => $data['booker_first_name'],
                        'booker_last_name' => $data['booker_last_name'],
                        'booker_phone' => $data['booker_phone'],
                        'confirmation_channel' => ConfirmationChannel::from($data['confirmation_channel']),
                        'confirmation_notes' => $data['confirmation_notes'],
                        'override_reason' => $data['override_reason'] ?? null,
                    ];

                    $booking = app(ManualBookingService::class)->createManualAirportPickup($manualData, auth()->user());
                    Notification::make()->title("Manual booking {$booking->reference} created.")->success()->send();
                }),

            Action::make('manual_transfer_booking')
                ->label('New Manual Transfer')
                ->icon('heroicon-o-arrow-up-right')
                ->color('gray')
                ->visible(fn (): bool => auth()->user()?->can(Permission::BookingConfirm->value) ?? false)
                ->modalHeading('Create Manual Airport Transfer')
                ->modalDescription('Record a direct departure journey to airport.')
                ->form([
                    Select::make('source')
                        ->label('Intake Source')
                        ->options([
                            BookingSource::ManualPhone->value => BookingSource::ManualPhone->label(),
                            BookingSource::ManualWhatsApp->value => BookingSource::ManualWhatsApp->label(),
                            BookingSource::ManualWalkin->value => BookingSource::ManualWalkin->label(),
                        ])
                        ->default(BookingSource::ManualPhone->value)
                        ->required(),

                    Select::make('vehicle_id')
                        ->label('Assigned Vehicle')
                        ->options(fn () => Vehicle::where('status', VehicleStatus::Active)
                            ->where('eligible_for_transfer', true)
                            ->get()
                            ->mapWithKeys(fn (Vehicle $v) => [$v->id => "{$v->make} {$v->model} ({$v->license_plate}) - {$v->passenger_capacity}p/{$v->luggage_capacity}b"]))
                        ->required(),

                    TextInput::make('flight_number')
                        ->label('Departure Flight Number')
                        ->placeholder('e.g. EK651')
                        ->required(),

                    DateTimePicker::make('departure_time')
                        ->label('Flight Departure Time (Colombo)')
                        ->timezone('Asia/Colombo')
                        ->required(),

                    TextInput::make('origin_text')
                        ->label('Pickup Origin / Hotel')
                        ->placeholder('e.g. Galle Fort Heritage Villa, Galle')
                        ->required(),

                    DateTimePicker::make('chosen_pickup_time')
                        ->label('Chosen Pickup Time (Colombo)')
                        ->timezone('Asia/Colombo')
                        ->required(),

                    TextInput::make('passenger_count')
                        ->numeric()
                        ->default(2)
                        ->minValue(1)
                        ->required(),

                    TextInput::make('luggage_count')
                        ->numeric()
                        ->default(2)
                        ->minValue(0)
                        ->required(),

                    TextInput::make('booker_first_name')
                        ->label('Customer First Name')
                        ->required(),

                    TextInput::make('booker_last_name')
                        ->label('Customer Last Name')
                        ->required(),

                    TextInput::make('booker_phone')
                        ->label('Customer Phone / WhatsApp (with country code)')
                        ->placeholder('+44 ...')
                        ->required(),

                    Select::make('confirmation_channel')
                        ->label('Confirmation Channel')
                        ->options(collect(ConfirmationChannel::cases())->mapWithKeys(fn (ConfirmationChannel $c) => [$c->value => $c->label()]))
                        ->default(ConfirmationChannel::Phone->value)
                        ->required(),

                    Textarea::make('confirmation_notes')
                        ->label('Intake & Communication Notes')
                        ->required()
                        ->rows(2),

                    Textarea::make('override_reason')
                        ->label('Conflict Override Reason (If schedule conflict exists)')
                        ->visible(fn () => auth()->user()?->can(Permission::BookingOverrideConflict->value) ?? false)
                        ->rows(2),
                ])
                ->action(function (array $data): void {
                    $departureTime = Carbon::parse($data['departure_time'])->setTimezone('Asia/Colombo');
                    $chosenPickupTime = Carbon::parse($data['chosen_pickup_time'])->setTimezone('Asia/Colombo');

                    $calc = app(AirportTransferRecommendationCalculator::class);
                    $defaultDuration = (int) config('booking.airport_transfer.default_trip_duration_minutes', 180);
                    $recommendedPickupTime = $calc->calculateRecommendedPickupTime($departureTime, $defaultDuration);
                    $isUnsafe = $calc->isUnsafeChoice($departureTime, $chosenPickupTime, $defaultDuration);

                    $criteria = new AirportTransferCriteria(
                        airport: 'CMB',
                        flightNumber: strtoupper(trim($data['flight_number'])),
                        departureTime: $departureTime,
                        originText: trim($data['origin_text']),
                        passengerCount: (int) $data['passenger_count'],
                        luggageCount: (int) $data['luggage_count'],
                        recommendedPickupTime: $recommendedPickupTime,
                        chosenPickupTime: $chosenPickupTime,
                        isUnsafeTime: $isUnsafe,
                        warningAccepted: true,
                    );

                    $manualData = [
                        'source' => BookingSource::from($data['source']),
                        'criteria' => $criteria,
                        'vehicle_id' => (int) $data['vehicle_id'],
                        'estimate_reference' => null,
                        'booker_first_name' => $data['booker_first_name'],
                        'booker_last_name' => $data['booker_last_name'],
                        'booker_phone' => $data['booker_phone'],
                        'confirmation_channel' => ConfirmationChannel::from($data['confirmation_channel']),
                        'confirmation_notes' => $data['confirmation_notes'],
                        'override_reason' => $data['override_reason'] ?? null,
                    ];

                    $booking = app(ManualBookingService::class)->createManualAirportTransfer($manualData, auth()->user());
                    Notification::make()->title("Manual transfer booking {$booking->reference} created.")->success()->send();
                }),

            Action::make('manual_rent_booking')
                ->label('New Manual Rent')
                ->icon('heroicon-o-key')
                ->color('amber')
                ->visible(fn (): bool => auth()->user()?->can(Permission::BookingConfirm->value) ?? false)
                ->modalHeading('Create Manual Self-Drive Rent')
                ->modalDescription('Record a direct phone, WhatsApp, or walk-in self-drive hire reservation.')
                ->form([
                    Select::make('source')
                        ->label('Intake Source')
                        ->options([
                            BookingSource::ManualPhone->value => BookingSource::ManualPhone->label(),
                            BookingSource::ManualWhatsApp->value => BookingSource::ManualWhatsApp->label(),
                            BookingSource::ManualWalkin->value => BookingSource::ManualWalkin->label(),
                        ])
                        ->default(BookingSource::ManualPhone->value)
                        ->required(),

                    Select::make('vehicle_id')
                        ->label('Assigned Vehicle')
                        ->options(fn () => Vehicle::where('status', VehicleStatus::Active)
                            ->where('eligible_for_rent', true)
                            ->get()
                            ->mapWithKeys(fn (Vehicle $v) => [$v->id => "{$v->make} {$v->model} ({$v->license_plate}) - {$v->passenger_capacity} seats"]))
                        ->required(),

                    DateTimePicker::make('pickup_datetime')
                        ->label('Pickup Date & Time (Colombo)')
                        ->timezone('Asia/Colombo')
                        ->default(now('Asia/Colombo')->addDay())
                        ->required(),

                    DateTimePicker::make('return_datetime')
                        ->label('Return Date & Time (Colombo)')
                        ->timezone('Asia/Colombo')
                        ->default(now('Asia/Colombo')->addDays(4))
                        ->required(),

                    Select::make('pickup_location_type')
                        ->label('Pickup Location Type')
                        ->options([
                            'office' => 'Colombo Head Office (Free)',
                            'airport' => 'Airport CMB (Free)',
                            'hotel' => 'Hotel Delivery ($20 USD)',
                        ])
                        ->default('office')
                        ->required(),

                    TextInput::make('pickup_location_text')
                        ->label('Pickup Specific Location')
                        ->default('Spinellanka Head Office, Colombo 03')
                        ->required(),

                    Select::make('return_location_type')
                        ->label('Return Location Type')
                        ->options([
                            'office' => 'Colombo Head Office (Free)',
                            'airport' => 'Airport CMB (Free)',
                            'hotel' => 'Hotel Collection',
                        ])
                        ->default('office')
                        ->required(),

                    TextInput::make('return_location_text')
                        ->label('Return Specific Location')
                        ->default('Spinellanka Head Office, Colombo 03')
                        ->required(),

                    TextInput::make('driver_age')
                        ->label('Lead Driver Age')
                        ->numeric()
                        ->default(30)
                        ->required(),

                    TextInput::make('driver_licence_country')
                        ->label('Licence Country')
                        ->default('United Kingdom')
                        ->required(),

                    TextInput::make('driver_licence_years')
                        ->label('Years Licence Held')
                        ->numeric()
                        ->default(5)
                        ->required(),

                    TextInput::make('booker_first_name')
                        ->label('Customer First Name')
                        ->required(),

                    TextInput::make('booker_last_name')
                        ->label('Customer Last Name')
                        ->required(),

                    TextInput::make('booker_phone')
                        ->label('Customer Phone / WhatsApp (with international prefix)')
                        ->placeholder('+44 ...')
                        ->required(),

                    Select::make('confirmation_channel')
                        ->label('Confirmation Channel')
                        ->options(collect(ConfirmationChannel::cases())->mapWithKeys(fn (ConfirmationChannel $c) => [$c->value => $c->label()]))
                        ->default(ConfirmationChannel::Phone->value)
                        ->required(),

                    Textarea::make('confirmation_notes')
                        ->label('Intake & Communication Notes')
                        ->required()
                        ->rows(2),

                    Textarea::make('override_reason')
                        ->label('Conflict Override Reason (If schedule conflict exists)')
                        ->visible(fn () => auth()->user()?->can(Permission::BookingOverrideConflict->value) ?? false)
                        ->rows(2),
                ])
                ->action(function (array $data): void {
                    $pickupTime = Carbon::parse($data['pickup_datetime'])->setTimezone('Asia/Colombo');
                    $returnTime = Carbon::parse($data['return_datetime'])->setTimezone('Asia/Colombo');

                    $calc = app(RentDurationCalculator::class);
                    $policy = RentPolicy::current();
                    $duration = $calc->calculate($pickupTime, $returnTime, $policy);

                    $criteria = new RentCriteria(
                        pickupDateTime: $pickupTime,
                        returnDateTime: $returnTime,
                        pickupLocationType: $data['pickup_location_type'],
                        pickupLocationText: trim($data['pickup_location_text']),
                        returnLocationType: $data['return_location_type'],
                        returnLocationText: trim($data['return_location_text']),
                        driverAge: (int) $data['driver_age'],
                        driverLicenceCountry: trim($data['driver_licence_country']),
                        driverLicenceYears: (int) $data['driver_licence_years'],
                        hasIdp: true,
                        durationDays: $duration->days,
                        extraHours: $duration->extraHours,
                    );

                    $manualData = [
                        'source' => BookingSource::from($data['source']),
                        'criteria' => $criteria,
                        'vehicle_id' => (int) $data['vehicle_id'],
                        'estimate_reference' => null,
                        'booker_first_name' => $data['booker_first_name'],
                        'booker_last_name' => $data['booker_last_name'],
                        'booker_phone' => $data['booker_phone'],
                        'confirmation_channel' => ConfirmationChannel::from($data['confirmation_channel']),
                        'confirmation_notes' => $data['confirmation_notes'],
                        'override_reason' => $data['override_reason'] ?? null,
                    ];

                    $booking = app(ManualBookingService::class)->createManualRent($manualData, auth()->user());
                    Notification::make()->title("Manual rent booking {$booking->reference} created.")->success()->send();
                }),
        ];
    }
}
