<?php

declare(strict_types=1);

namespace App\Domain\Messaging\Services;

use App\Domain\Booking\Enums\ContactType;
use App\Domain\Booking\ServiceType;
use App\Models\AirportPickupDetail;
use App\Models\AirportTransferDetail;
use App\Models\Booking;
use App\Models\RentDetail;
use App\Models\RideDetail;

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

        $pickupDetail = $booking->airportPickupDetail;
        $transferDetail = $booking->airportTransferDetail;
        $rideDetail = $booking->rideDetail;
        $rentDetail = $booking->rentDetail;

        $originText = match ($booking->service_type) {
            ServiceType::AirportPickup => 'Colombo BIA (CMB)',
            ServiceType::AirportTransfer => $transferDetail instanceof AirportTransferDetail ? $transferDetail->origin_text : 'Pickup Location',
            ServiceType::Ride => $rideDetail instanceof RideDetail ? $rideDetail->origin_text : 'Pickup Location',
            ServiceType::Rent => $rentDetail instanceof RentDetail ? $rentDetail->pickup_location_text : 'Spinellanka Office',
        };

        $destinationText = match ($booking->service_type) {
            ServiceType::AirportPickup => $pickupDetail instanceof AirportPickupDetail ? $pickupDetail->destination_text : 'Destination',
            ServiceType::AirportTransfer => 'Colombo BIA (CMB)',
            ServiceType::Ride => $rideDetail instanceof RideDetail ? $rideDetail->destination_text : 'Destination',
            ServiceType::Rent => $rentDetail instanceof RentDetail ? $rentDetail->return_location_text : 'Spinellanka Office',
        };

        $vehicleDisplay = $booking->vehicle?->public_name
            ?: ($booking->vehicle ? "{$booking->vehicle->make} {$booking->vehicle->model}" : 'Reserved Vehicle');

        $booker = $booking->contacts->firstWhere('type', ContactType::Booker) ?? $booking->booker;
        $customerName = $booker?->first_name ?: 'Valued Guest';

        $statusUrl = (string) ($extraPayload['status_url'] ?? '');
        $supportContact = (string) (config('services.messaging.support_contact') ?? config('booking.support_phone', 'support@spinellanka.com'));

        $reason = (string) ($extraPayload['decline_reason']
            ?? $extraPayload['cancellation_reason']
            ?? $extraPayload['reason']
            ?? '');

        return [
            'customer_name' => $customerName,
            'reference' => $booking->reference,
            'service_title' => $serviceTitle,
            'service_start' => $serviceStart,
            'vehicle_display' => $vehicleDisplay,
            'origin_text' => $originText,
            'destination_text' => $destinationText,
            'status_url' => $statusUrl,
            'support_contact' => $supportContact,
            'reason' => $reason,
        ];
    }

    /**
     * Format parameters into the strict positional sequence expected by Meta WhatsApp Cloud API templates.
     *
     * @param  array<string, mixed>  $params
     * @return list<string>
     */
    public function formatMetaParams(string $templateName, array $params): array
    {
        return match ($templateName) {
            'booking_submitted_v1' => [
                ! empty($params['customer_name']) ? (string) $params['customer_name'] : 'Valued Guest',
                ! empty($params['reference']) ? (string) $params['reference'] : 'Booking',
                ! empty($params['service_title']) ? (string) $params['service_title'] : 'Chauffeured Service',
                ! empty($params['service_start']) ? (string) $params['service_start'] : 'Scheduled Time',
                ! empty($params['status_url']) ? (string) $params['status_url'] : 'https://spinellanka.com',
            ],
            'booking_confirmed_v1' => [
                ! empty($params['customer_name']) ? (string) $params['customer_name'] : 'Valued Guest',
                ! empty($params['reference']) ? (string) $params['reference'] : 'Booking',
                ! empty($params['vehicle_display']) ? (string) $params['vehicle_display'] : 'Dedicated Fleet',
                ! empty($params['service_start']) ? (string) $params['service_start'] : 'Scheduled Time',
                ! empty($params['status_url']) ? (string) $params['status_url'] : 'https://spinellanka.com',
            ],
            'booking_declined_v1', 'booking_cancelled_v1' => [
                ! empty($params['customer_name']) ? (string) $params['customer_name'] : 'Valued Guest',
                ! empty($params['reference']) ? (string) $params['reference'] : 'Booking',
                ! empty($params['reason']) ? (string) $params['reason'] : 'Fleet schedule constraints.',
            ],
            'staff_new_booking_alert_v1' => [
                ! empty($params['reference']) ? (string) $params['reference'] : 'Booking',
                ! empty($params['service_title']) ? (string) $params['service_title'] : 'Reservation',
                ! empty($params['service_start']) ? (string) $params['service_start'] : 'Scheduled Time',
                ! empty($params['vehicle_display']) ? (string) $params['vehicle_display'] : 'Assigned Vehicle',
            ],
            default => array_values(array_map('strval', array_filter($params, fn ($v) => $v !== null && $v !== ''))),
        };
    }
}
