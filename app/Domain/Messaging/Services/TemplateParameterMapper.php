<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Services;

use App\Domain\Booking\ServiceType;
use App\Models\Booking;

class TemplateParameterMapper
{
    /**
     * Map a booking into standardized notification template parameters.
     *
     * @param  array<string, mixed>  $extraPayload
     * @return array<string, mixed>
     */
    public function mapBookingParams(Booking $booking, array $extraPayload = []): array
    {
        $serviceTitle = match ($booking->service_type) {
            ServiceType::AirportPickup => 'Airport Pickup',
            ServiceType::AirportTransfer => 'Airport Transfer',
            ServiceType::Ride => 'Chauffeured Ride',
            ServiceType::Rent => 'Self-Drive Vehicle Hire',
        };

        $serviceStart = match ($booking->service_type) {
            ServiceType::AirportPickup => $booking->airportPickupDetail?->arrival_time?->setTimezone('Asia/Colombo')->format('D, d M Y @ H:i') ?? 'Scheduled Arrival',
            ServiceType::AirportTransfer => $booking->airportTransferDetail?->chosen_pickup_time?->setTimezone('Asia/Colombo')->format('D, d M Y @ H:i') ?? 'Scheduled Pickup',
            ServiceType::Ride => $booking->rideDetail?->pickup_time?->setTimezone('Asia/Colombo')->format('D, d M Y @ H:i') ?? 'Scheduled Journey',
            ServiceType::Rent => $booking->rentDetail?->pickup_datetime?->setTimezone('Asia/Colombo')->format('D, d M Y @ H:i') ?? 'Scheduled Handover',
        };

        $vehicleDisplay = $booking->vehicle?->public_name
            ?: ($booking->vehicle ? "{$booking->vehicle->make} {$booking->vehicle->model}" : 'Reserved Vehicle');

        $booker = $booking->booker;
        $customerName = $booker?->first_name ?: 'Valued Guest';

        $statusUrl = $extraPayload['status_url'] ?? '';

        return [
            'customer_name' => $customerName,
            'reference' => $booking->reference,
            'service_title' => $serviceTitle,
            'service_start' => $serviceStart,
            'vehicle_display' => $vehicleDisplay,
            'status_url' => $statusUrl,
            'support_contact' => config('booking.support_phone', '+94 77 123 4567'),
            'reason' => $extraPayload['reason'] ?? $extraPayload['decline_reason'] ?? $extraPayload['notes'] ?? '',
        ];
    }
}
