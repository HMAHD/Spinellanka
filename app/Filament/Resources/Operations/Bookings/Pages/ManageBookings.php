<?php

declare(strict_types=1);

namespace App\Filament\Resources\Operations\Bookings\Pages;

use App\Domain\Booking\DTOs\AirportPickupCriteria;
use App\Domain\Booking\Enums\BookingSource;
use App\Domain\Booking\Enums\ConfirmationChannel;
use App\Domain\Booking\Services\ManualBookingService;
use App\Domain\Catalog\Enums\VehicleStatus;
use App\Domain\Identity\Permission;
use App\Filament\Resources\Operations\Bookings\BookingResource;
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
        ];
    }
}
