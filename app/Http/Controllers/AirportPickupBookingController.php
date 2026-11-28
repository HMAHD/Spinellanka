<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Availability\Exceptions\ScheduleConflictException;
use App\Domain\Availability\Services\AvailabilityService;
use App\Domain\Booking\DTOs\AirportPickupCriteria;
use App\Domain\Booking\DTOs\SubmitAirportPickupData;
use App\Domain\Booking\Services\AirportPickupSuitabilityService;
use App\Domain\Booking\Services\BookingSubmissionService;
use App\Domain\Booking\ServiceType;
use App\Domain\Catalog\Enums\VehicleStatus;
use App\Domain\Location\Contracts\PlaceSearchContract;
use App\Domain\Location\Contracts\RouteEstimateContract;
use App\Domain\Location\DTOs\LocationCoordinate;
use App\Domain\Pricing\DTOs\EstimateRequestData;
use App\Domain\Pricing\Services\EstimateService;
use App\Domain\Pricing\Services\PricingService;
use App\Models\Estimate;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;

class AirportPickupBookingController extends Controller
{
    public function __construct(
        protected AirportPickupSuitabilityService $suitabilityService,
        protected AvailabilityService $availabilityService,
        protected PricingService $pricingService,
        protected EstimateService $estimateService,
        protected BookingSubmissionService $submissionService,
        protected PlaceSearchContract $placeSearchService,
        protected RouteEstimateContract $routeEstimateService,
    ) {}

    public function create(Request $request): Response
    {
        $criteria = $request->session()->get('airport_pickup_criteria');
        $dealPreset = $request->session()->get('booking_deal_preset');

        if ($criteria === null && is_array($dealPreset) && ($dealPreset['service_type'] ?? null) === ServiceType::AirportPickup->value) {
            $travelDate = isset($dealPreset['travel_date']) ? (string) $dealPreset['travel_date'] : Carbon::now('Asia/Colombo')->addDays(2)->format('Y-m-d');
            $travelTime = isset($dealPreset['travel_time']) ? (string) $dealPreset['travel_time'] : '14:30';
            $criteria = new AirportPickupCriteria(
                airport: (string) ($dealPreset['airport'] ?? 'CMB'),
                flightNumber: (string) ($dealPreset['flight_number'] ?? 'UL504'),
                arrivalTime: Carbon::parse("{$travelDate} {$travelTime}", 'Asia/Colombo'),
                destinationText: (string) ($dealPreset['destination'] ?? $dealPreset['destination_text'] ?? 'Galle Fort'),
                passengerCount: (int) ($dealPreset['passenger_count'] ?? 2),
                luggageCount: (int) ($dealPreset['luggage_count'] ?? 2),
            );
        }

        $places = $this->placeSearchService->search('airport');

        return response()->view('booking.airport-pickup.create', [
            'criteria' => $criteria,
            'places' => $places,
            'step' => 1,
        ]);
    }

    public function search(Request $request): Response|RedirectResponse
    {
        $validated = $request->validate([
            'airport' => ['required', 'string', 'max:16'],
            'flight_number' => ['required', 'string', 'max:32'],
            'arrival_date' => ['required', 'date_format:Y-m-d'],
            'arrival_time' => ['required', 'date_format:H:i'],
            'destination_text' => ['required', 'string', 'max:500'],
            'passenger_count' => ['required', 'integer', 'min:1', 'max:10'],
            'luggage_count' => ['required', 'integer', 'min:0', 'max:10'],
            'place_id' => ['nullable', 'string', 'max:255'],
            'destination_notes' => ['nullable', 'string', 'max:1000'],
            'meet_and_greet' => ['nullable', 'boolean'],
            'name_board_text' => ['nullable', 'string', 'max:255'],
        ]);

        $arrivalDateTime = Carbon::createFromFormat(
            'Y-m-d H:i',
            "{$validated['arrival_date']} {$validated['arrival_time']}",
            'Asia/Colombo'
        );

        if (! $arrivalDateTime) {
            throw new InvalidArgumentException('Invalid flight arrival date and time.');
        }

        // Distance & duration estimation
        $distanceKm = null;
        $durationHours = null;
        $destLat = null;
        $destLng = null;

        if (! empty($validated['place_id'])) {
            try {
                $placeDetails = $this->placeSearchService->getDetails($validated['place_id']);
                if ($placeDetails && $placeDetails->coordinate) {
                    $destLat = $placeDetails->coordinate->latitude;
                    $destLng = $placeDetails->coordinate->longitude;

                    // CMB coordinates
                    $origin = new LocationCoordinate(7.1808, 79.8841);
                    $routeResult = $this->routeEstimateService->estimateRoute($origin, $placeDetails->coordinate);
                    if ($routeResult) {
                        $distanceKm = $routeResult->distanceKm;
                        $durationHours = round($routeResult->durationMinutes / 60.0, 1);
                    }
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        $criteria = new AirportPickupCriteria(
            airport: $validated['airport'],
            flightNumber: strtoupper(trim($validated['flight_number'])),
            arrivalTime: $arrivalDateTime,
            destinationText: trim($validated['destination_text']),
            passengerCount: (int) $validated['passenger_count'],
            luggageCount: (int) $validated['luggage_count'],
            distanceKm: $distanceKm,
            durationHours: $durationHours,
            placeId: $validated['place_id'] ?? null,
            destinationLat: $destLat,
            destinationLng: $destLng,
            destinationNotes: $validated['destination_notes'] ?? null,
            meetAndGreet: (bool) ($validated['meet_and_greet'] ?? true),
            nameBoardText: $validated['name_board_text'] ?? null,
        );

        // Store criteria in session
        $request->session()->put('airport_pickup_criteria', $criteria);

        return redirect()->route('booking.airport-pickup.vehicles');
    }

    public function vehicles(Request $request): Response|RedirectResponse
    {
        /** @var AirportPickupCriteria|null $criteria */
        $criteria = $request->session()->get('airport_pickup_criteria');
        if (! $criteria && app()->environment('local', 'testing') && $request->has('preview')) {
            $criteria = new AirportPickupCriteria(
                airport: 'CMB',
                flightNumber: 'UL504',
                arrivalTime: Carbon::now('Asia/Colombo')->addDays(3)->setTime(14, 30),
                destinationText: 'Heritance Ahungalla, Galle Road',
                passengerCount: 3,
                luggageCount: 3,
                meetAndGreet: true,
                nameBoardText: 'Dr. Livingston & Family',
            );
        }

        if (! $criteria) {
            return redirect()->route('booking.airport-pickup')
                ->with('error', 'Flight arrival details expired. Please enter criteria again.');
        }

        $suitability = $this->suitabilityService->evaluate($criteria);

        return response()->view('booking.airport-pickup.vehicles', [
            'criteria' => $criteria,
            'suitable' => $suitability->suitable,
            'unsuitable' => $suitability->unsuitable,
            'step' => 2,
        ]);
    }

    public function hold(Request $request): Response|RedirectResponse
    {
        $validated = $request->validate([
            'vehicle_id' => ['required', 'integer', 'exists:vehicles,id'],
        ]);

        /** @var AirportPickupCriteria|null $criteria */
        $criteria = $request->session()->get('airport_pickup_criteria');
        if (! $criteria) {
            return redirect()->route('booking.airport-pickup')
                ->with('error', 'Flight arrival details expired. Please enter criteria again.');
        }

        $vehicle = Vehicle::where('id', $validated['vehicle_id'])->firstOrFail();

        if (! $vehicle->eligible_for_pickup || $vehicle->status !== VehicleStatus::Active) {
            return redirect()->route('booking.airport-pickup.vehicles')
                ->with('error', 'Selected vehicle is not eligible for Airport Pickup.');
        }

        // Release any existing hold before establishing a new one
        $previousHold = $request->session()->get('airport_pickup_hold');
        if ($previousHold && ! empty($previousHold['plain_token'])) {
            try {
                $this->availabilityService->releaseHold($previousHold['plain_token'], 'vehicle_reselected');
            } catch (\Throwable) {
                // Ignore failure on releasing expired or converted previous hold
            }
        }

        // Calculate estimate snapshot
        $startsAt = (clone $criteria->arrivalTime)->setTimezone('Asia/Colombo');
        $tripDurationMinutes = $criteria->durationHours !== null
            ? (int) round($criteria->durationHours * 60)
            : (int) config('booking.airport_pickup.default_trip_duration_minutes', 180);

        $endsAt = (clone $startsAt)->addMinutes($tripDurationMinutes);

        $dealPreset = $request->session()->get('booking_deal_preset');
        $dealId = is_array($dealPreset) && isset($dealPreset['deal_id']) ? (int) $dealPreset['deal_id'] : null;

        $estimateRequest = new EstimateRequestData(
            serviceType: ServiceType::AirportPickup,
            vehicleId: $vehicle->id,
            serviceDate: $startsAt,
            distanceKm: $criteria->distanceKm,
            durationHours: $criteria->durationHours,
            dealId: $dealId,
            assumptions: [
                'airport' => $criteria->airport,
                'flight_number' => $criteria->flightNumber,
                'destination' => $criteria->destinationText,
            ]
        );

        $estimateResult = $this->pricingService->calculateEstimate($estimateRequest);
        $estimate = $this->estimateService->snapshotEstimate($estimateResult, $estimateRequest);

        // Create 15-minute checkout hold
        $holdResult = $this->availabilityService->createHold(
            vehicle: $vehicle,
            start: $startsAt,
            end: $endsAt,
            service: ServiceType::AirportPickup,
        );

        // Store checkout state in session
        $request->session()->put('airport_pickup_hold', [
            'vehicle_id' => $vehicle->id,
            'plain_token' => $holdResult->plainToken,
            'hold_id' => $holdResult->hold->id,
            'expires_at' => $holdResult->hold->expires_at->toIso8601String(),
            'estimate_reference' => $estimate->reference,
        ]);

        return redirect()->route('booking.airport-pickup.contact');
    }

    public function contact(Request $request): Response|RedirectResponse
    {
        /** @var AirportPickupCriteria|null $criteria */
        $criteria = $request->session()->get('airport_pickup_criteria');
        $holdSession = $request->session()->get('airport_pickup_hold');

        if ((! $criteria || ! $holdSession) && app()->environment('local', 'testing') && $request->has('preview')) {
            $criteria = new AirportPickupCriteria(
                airport: 'CMB',
                flightNumber: 'UL504',
                arrivalTime: Carbon::now('Asia/Colombo')->addDays(3)->setTime(14, 30),
                destinationText: 'Heritance Ahungalla, Galle Road',
                passengerCount: 3,
                luggageCount: 3,
                meetAndGreet: true,
                nameBoardText: 'Dr. Livingston & Family',
            );
            $vehicle = Vehicle::with(['media', 'publicApprovedMedia'])
                ->where('status', VehicleStatus::Active)
                ->where('eligible_for_pickup', true)
                ->first();
            $estimate = Estimate::where('service_type', ServiceType::AirportPickup)->latest()->first();
            $holdExpiresAt = Carbon::now('Asia/Colombo')->addMinutes(15);
            if ($vehicle && $estimate) {
                return response()->view('booking.airport-pickup.contact', [
                    'criteria' => $criteria,
                    'vehicle' => $vehicle,
                    'estimate' => $estimate,
                    'holdExpiresAt' => $holdExpiresAt,
                    'step' => 3,
                ]);
            }
        }

        if (! $criteria || ! $holdSession) {
            return redirect()->route('booking.airport-pickup')
                ->with('error', 'Checkout hold expired. Please reselect your vehicle.');
        }

        $vehicle = Vehicle::with(['media', 'publicApprovedMedia'])
            ->where('id', $holdSession['vehicle_id'])
            ->first();
        $estimate = $this->estimateService->findByReference($holdSession['estimate_reference']);

        if (! $vehicle || ! $estimate) {
            return redirect()->route('booking.airport-pickup')
                ->with('error', 'Selected vehicle or estimate expired. Please reselect your vehicle.');
        }

        $holdExpiresAt = Carbon::parse($holdSession['expires_at']);
        if ($holdExpiresAt->isPast()) {
            return redirect()->route('booking.airport-pickup.vehicles')
                ->with('error', '15-minute checkout hold has expired. Please choose a vehicle again.');
        }

        return response()->view('booking.airport-pickup.contact', [
            'criteria' => $criteria,
            'vehicle' => $vehicle,
            'estimate' => $estimate,
            'holdExpiresAt' => $holdExpiresAt,
            'step' => 3,
        ]);
    }

    public function submit(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'booker_first_name' => ['required', 'string', 'max:128'],
            'booker_last_name' => ['required', 'string', 'max:128'],
            'booker_phone' => ['required', 'string', 'max:32'],
            'booker_is_whatsapp' => ['nullable', 'boolean'],
            'booker_email' => ['nullable', 'email', 'max:255'],
            'booker_marketing_consent' => ['nullable', 'boolean'],
            'lead_traveler_same' => ['nullable', 'boolean'],
            'lead_traveler_first_name' => ['required_if:lead_traveler_same,0', 'nullable', 'string', 'max:128'],
            'lead_traveler_last_name' => ['required_if:lead_traveler_same,0', 'nullable', 'string', 'max:128'],
            'lead_traveler_phone' => ['required_if:lead_traveler_same,0', 'nullable', 'string', 'max:32'],
            'lead_traveler_is_whatsapp' => ['nullable', 'boolean'],
            'lead_traveler_email' => ['nullable', 'email', 'max:255'],
            'terms_accepted' => ['accepted'],
            'privacy_accepted' => ['accepted'],
        ]);

        /** @var AirportPickupCriteria|null $criteria */
        $criteria = $request->session()->get('airport_pickup_criteria');
        $holdSession = $request->session()->get('airport_pickup_hold');

        if (! $criteria || ! $holdSession) {
            return redirect()->route('booking.airport-pickup')
                ->with('error', 'Checkout hold expired. Please reselect your vehicle.');
        }

        $leadTravelerSame = $request->boolean('lead_traveler_same');

        $submitData = new SubmitAirportPickupData(
            criteria: $criteria,
            vehicleId: (int) $holdSession['vehicle_id'],
            holdPlainToken: (string) $holdSession['plain_token'],
            estimateReference: (string) $holdSession['estimate_reference'],
            bookerFirstName: $validated['booker_first_name'],
            bookerLastName: $validated['booker_last_name'],
            bookerPhone: $validated['booker_phone'],
            bookerIsWhatsapp: (bool) ($validated['booker_is_whatsapp'] ?? true),
            bookerEmail: $validated['booker_email'] ?? null,
            bookerMarketingConsent: (bool) ($validated['booker_marketing_consent'] ?? false),
            leadTravelerSameAsBooker: $leadTravelerSame,
            leadTravelerFirstName: $leadTravelerSame ? null : $validated['lead_traveler_first_name'],
            leadTravelerLastName: $leadTravelerSame ? null : $validated['lead_traveler_last_name'],
            leadTravelerPhone: $leadTravelerSame ? null : $validated['lead_traveler_phone'],
            leadTravelerIsWhatsapp: (bool) ($validated['lead_traveler_is_whatsapp'] ?? true),
            leadTravelerEmail: $validated['lead_traveler_email'] ?? null,
            termsAccepted: true,
            privacyAccepted: true,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        try {
            $result = $this->submissionService->submitAirportPickup($submitData);
        } catch (ScheduleConflictException|InvalidArgumentException $e) {
            return redirect()->route('booking.airport-pickup.vehicles')
                ->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('booking.airport-pickup.vehicles')
                ->with('error', 'Unable to complete your advance request. Please reselect your vehicle.');
        }

        // Clear booking session
        $request->session()->forget(['airport_pickup_criteria', 'airport_pickup_hold']);

        return redirect()->to($result->privateUrl);
    }
}
