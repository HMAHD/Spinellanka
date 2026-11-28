<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Availability\Exceptions\ScheduleConflictException;
use App\Domain\Availability\Services\AvailabilityService;
use App\Domain\Booking\DTOs\AirportTransferCriteria;
use App\Domain\Booking\DTOs\SubmitAirportTransferData;
use App\Domain\Booking\Services\AirportTransferRecommendationCalculator;
use App\Domain\Booking\Services\AirportTransferSuitabilityService;
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

class AirportTransferBookingController extends Controller
{
    public function __construct(
        protected AirportTransferSuitabilityService $suitabilityService,
        protected AirportTransferRecommendationCalculator $calculator,
        protected AvailabilityService $availabilityService,
        protected PricingService $pricingService,
        protected EstimateService $estimateService,
        protected BookingSubmissionService $submissionService,
        protected PlaceSearchContract $placeSearchService,
        protected RouteEstimateContract $routeEstimateService,
    ) {}

    public function create(Request $request): Response
    {
        $criteria = $request->session()->get('airport_transfer_criteria');
        $dealPreset = $request->session()->get('booking_deal_preset');

        if ($criteria === null && is_array($dealPreset) && ($dealPreset['service_type'] ?? null) === ServiceType::AirportTransfer->value) {
            $travelDate = isset($dealPreset['travel_date']) ? (string) $dealPreset['travel_date'] : Carbon::now('Asia/Colombo')->addDays(2)->format('Y-m-d');
            $travelTime = isset($dealPreset['travel_time']) ? (string) $dealPreset['travel_time'] : '18:00';
            $departureTime = Carbon::parse("{$travelDate} {$travelTime}", 'Asia/Colombo');
            $recommendedPickup = (clone $departureTime)->subHours(4);

            $criteria = new AirportTransferCriteria(
                airport: (string) ($dealPreset['airport'] ?? 'CMB'),
                flightNumber: (string) ($dealPreset['flight_number'] ?? 'UL505'),
                departureTime: $departureTime,
                originText: (string) ($dealPreset['origin'] ?? $dealPreset['origin_text'] ?? 'Galle Fort'),
                passengerCount: (int) ($dealPreset['passenger_count'] ?? 2),
                luggageCount: (int) ($dealPreset['luggage_count'] ?? 2),
                recommendedPickupTime: $recommendedPickup,
                chosenPickupTime: $recommendedPickup,
            );
        }

        $places = $this->placeSearchService->search('hotel');

        return response()->view('booking.airport-transfer.create', [
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
            'departure_date' => ['required', 'date_format:Y-m-d'],
            'departure_time' => ['required', 'date_format:H:i'],
            'origin_text' => ['required', 'string', 'max:500'],
            'passenger_count' => ['required', 'integer', 'min:1', 'max:10'],
            'luggage_count' => ['required', 'integer', 'min:0', 'max:10'],
            'place_id' => ['nullable', 'string', 'max:255'],
            'origin_notes' => ['nullable', 'string', 'max:1000'],
            'special_instructions' => ['nullable', 'string', 'max:1000'],
            'pickup_timing_mode' => ['nullable', 'string', 'in:recommended,custom'],
            'custom_pickup_date' => ['nullable', 'date_format:Y-m-d'],
            'custom_pickup_time' => ['nullable', 'date_format:H:i'],
            'warning_accepted' => ['nullable', 'boolean'],
        ]);

        $departureDateTime = Carbon::createFromFormat(
            'Y-m-d H:i',
            "{$validated['departure_date']} {$validated['departure_time']}",
            'Asia/Colombo'
        );

        if (! $departureDateTime) {
            throw new InvalidArgumentException('Invalid flight departure date and time.');
        }

        $minNoticeHours = (int) config('booking.airport_transfer.minimum_notice_hours', 6);
        if ($departureDateTime->lt(Carbon::now('Asia/Colombo')->addHours($minNoticeHours))) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['departure_time' => "Minimum notice of {$minNoticeHours} hours required before flight departure."]);
        }

        // Distance & duration estimation from origin to CMB
        $distanceKm = null;
        $durationHours = null;
        $durationMinutes = (int) config('booking.airport_transfer.default_trip_duration_minutes', 180);
        $originLat = null;
        $originLng = null;

        if (! empty($validated['place_id'])) {
            try {
                $placeDetails = $this->placeSearchService->getDetails($validated['place_id']);
                if ($placeDetails && $placeDetails->coordinate) {
                    $originLat = $placeDetails->coordinate->latitude;
                    $originLng = $placeDetails->coordinate->longitude;

                    // CMB coordinates (destination)
                    $airportCoord = new LocationCoordinate(7.1808, 79.8841);
                    $routeResult = $this->routeEstimateService->estimateRoute($placeDetails->coordinate, $airportCoord);
                    if ($routeResult) {
                        $distanceKm = $routeResult->distanceKm;
                        $durationMinutes = (int) round($routeResult->durationMinutes);
                        $durationHours = round($routeResult->durationMinutes / 60.0, 1);
                    }
                }
            } catch (\Throwable $e) {
                report($e);
            }
        }

        // Calculate timing recommendations
        $recommendedPickupTime = $this->calculator->calculateRecommendedPickupTime($departureDateTime, $durationMinutes);
        $latestAllowedPickupTime = $this->calculator->calculateLatestAllowedPickupTime($departureDateTime, $durationMinutes);

        $chosenPickupTime = clone $recommendedPickupTime;
        $isUnsafeTime = false;
        $warningAccepted = false;

        $timingMode = $validated['pickup_timing_mode'] ?? 'recommended';
        if ($timingMode === 'custom') {
            if (empty($validated['custom_pickup_date']) || empty($validated['custom_pickup_time'])) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['custom_pickup_time' => 'Please specify your preferred custom pickup date and time.']);
            }

            $parsedChosen = Carbon::createFromFormat(
                'Y-m-d H:i',
                "{$validated['custom_pickup_date']} {$validated['custom_pickup_time']}",
                'Asia/Colombo'
            );

            if (! $parsedChosen) {
                return redirect()->back()->withInput()->withErrors(['custom_pickup_time' => 'Invalid custom pickup time format.']);
            }

            // Check permissibility
            if (! $this->calculator->isPermissibleChoice($departureDateTime, $parsedChosen, $durationMinutes)) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors([
                        'custom_pickup_time' => "Selected pickup time ({$parsedChosen->format('H:i')}) is too close to departure ({$departureDateTime->format('H:i')}). The latest permissible pickup time for safe transit is {$latestAllowedPickupTime->format('H:i')}. Please select an earlier time or contact staff.",
                    ]);
            }

            $chosenPickupTime = $parsedChosen;

            if ($this->calculator->isUnsafeChoice($departureDateTime, $chosenPickupTime, $durationMinutes)) {
                $isUnsafeTime = true;
                $warningAccepted = $request->boolean('warning_accepted');

                if (! $warningAccepted) {
                    return redirect()->back()
                        ->withInput()
                        ->with('prompt_warning_acceptance', true)
                        ->withErrors([
                            'warning_accepted' => 'Your chosen pickup time provides less than our recommended 3-hour international arrival buffer. You must acknowledge and accept this risk to continue.',
                        ]);
                }
            }
        }

        $criteria = new AirportTransferCriteria(
            airport: $validated['airport'],
            flightNumber: strtoupper(trim($validated['flight_number'])),
            departureTime: $departureDateTime,
            originText: trim($validated['origin_text']),
            passengerCount: (int) $validated['passenger_count'],
            luggageCount: (int) $validated['luggage_count'],
            recommendedPickupTime: $recommendedPickupTime,
            chosenPickupTime: $chosenPickupTime,
            isUnsafeTime: $isUnsafeTime,
            warningAccepted: $warningAccepted,
            distanceKm: $distanceKm,
            durationHours: $durationHours,
            placeId: $validated['place_id'] ?? null,
            originLat: $originLat,
            originLng: $originLng,
            originNotes: $validated['origin_notes'] ?? null,
            specialInstructions: $validated['special_instructions'] ?? null,
        );

        $request->session()->put('airport_transfer_criteria', $criteria);

        return redirect()->route('booking.airport-transfer.vehicles');
    }

    public function vehicles(Request $request): Response|RedirectResponse
    {
        /** @var AirportTransferCriteria|null $criteria */
        $criteria = $request->session()->get('airport_transfer_criteria');
        if (! $criteria && app()->environment('local', 'testing') && $request->has('preview')) {
            $dep = Carbon::now('Asia/Colombo')->addDays(3)->setTime(21, 0);
            $rec = (clone $dep)->subMinutes(180 + 120 + 45);
            $criteria = new AirportTransferCriteria(
                airport: 'CMB',
                flightNumber: 'EK651',
                departureTime: $dep,
                originText: 'Heritance Ahungalla, Galle Road',
                passengerCount: 2,
                luggageCount: 2,
                recommendedPickupTime: $rec,
                chosenPickupTime: $rec,
                isUnsafeTime: false,
                warningAccepted: false,
            );
        }

        if (! $criteria) {
            return redirect()->route('booking.airport-transfer')
                ->with('error', 'Flight departure details expired. Please enter criteria again.');
        }

        $suitability = $this->suitabilityService->evaluate($criteria);

        return response()->view('booking.airport-transfer.vehicles', [
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

        /** @var AirportTransferCriteria|null $criteria */
        $criteria = $request->session()->get('airport_transfer_criteria');
        if (! $criteria) {
            return redirect()->route('booking.airport-transfer')
                ->with('error', 'Flight departure details expired. Please enter criteria again.');
        }

        $vehicle = Vehicle::where('id', $validated['vehicle_id'])->firstOrFail();

        if (! $vehicle->eligible_for_transfer || $vehicle->status !== VehicleStatus::Active) {
            return redirect()->route('booking.airport-transfer.vehicles')
                ->with('error', 'Selected vehicle is not eligible for Airport Transfer.');
        }

        // Release any existing hold before establishing a new one
        $previousHold = $request->session()->get('airport_transfer_hold');
        if ($previousHold && ! empty($previousHold['plain_token'])) {
            try {
                $this->availabilityService->releaseHold($previousHold['plain_token'], 'vehicle_reselected');
            } catch (\Throwable) {
                // Ignore failure on releasing expired or converted previous hold
            }
        }

        // Calculate estimate snapshot
        $startsAt = (clone $criteria->chosenPickupTime)->setTimezone('Asia/Colombo');
        $tripDurationMinutes = $criteria->durationHours !== null
            ? (int) round($criteria->durationHours * 60)
            : (int) config('booking.airport_transfer.default_trip_duration_minutes', 180);

        $endsAt = (clone $startsAt)->addMinutes($tripDurationMinutes);

        $dealPreset = $request->session()->get('booking_deal_preset');
        $dealId = is_array($dealPreset) && isset($dealPreset['deal_id']) ? (int) $dealPreset['deal_id'] : null;

        $estimateRequest = new EstimateRequestData(
            serviceType: ServiceType::AirportTransfer,
            vehicleId: $vehicle->id,
            serviceDate: $startsAt,
            distanceKm: $criteria->distanceKm,
            durationHours: $criteria->durationHours,
            dealId: $dealId,
            assumptions: [
                'airport' => $criteria->airport,
                'flight_number' => $criteria->flightNumber,
                'origin' => $criteria->originText,
                'departure_time' => $criteria->departureTime->toIso8601String(),
                'recommended_pickup_time' => $criteria->recommendedPickupTime->toIso8601String(),
                'chosen_pickup_time' => $criteria->chosenPickupTime->toIso8601String(),
                'is_unsafe_time' => $criteria->isUnsafeTime,
            ]
        );

        $estimateResult = $this->pricingService->calculateEstimate($estimateRequest);
        $estimate = $this->estimateService->snapshotEstimate($estimateResult, $estimateRequest);

        // Create 15-minute checkout hold
        $holdResult = $this->availabilityService->createHold(
            vehicle: $vehicle,
            start: $startsAt,
            end: $endsAt,
            service: ServiceType::AirportTransfer,
        );

        $request->session()->put('airport_transfer_hold', [
            'vehicle_id' => $vehicle->id,
            'plain_token' => $holdResult->plainToken,
            'hold_id' => $holdResult->hold->id,
            'expires_at' => $holdResult->hold->expires_at->toIso8601String(),
            'estimate_reference' => $estimate->reference,
        ]);

        return redirect()->route('booking.airport-transfer.contact');
    }

    public function contact(Request $request): Response|RedirectResponse
    {
        /** @var AirportTransferCriteria|null $criteria */
        $criteria = $request->session()->get('airport_transfer_criteria');
        $holdSession = $request->session()->get('airport_transfer_hold');

        if ((! $criteria || ! $holdSession) && app()->environment('local', 'testing') && $request->has('preview')) {
            $dep = Carbon::now('Asia/Colombo')->addDays(3)->setTime(21, 0);
            $rec = (clone $dep)->subMinutes(180 + 120 + 45);
            $criteria = new AirportTransferCriteria(
                airport: 'CMB',
                flightNumber: 'EK651',
                departureTime: $dep,
                originText: 'Heritance Ahungalla, Galle Road',
                passengerCount: 2,
                luggageCount: 2,
                recommendedPickupTime: $rec,
                chosenPickupTime: $rec,
                isUnsafeTime: false,
                warningAccepted: false,
            );
            $vehicle = Vehicle::with(['media', 'publicApprovedMedia'])
                ->where('status', VehicleStatus::Active)
                ->where('eligible_for_transfer', true)
                ->first();
            $estimate = Estimate::where('service_type', ServiceType::AirportTransfer)->latest()->first();
            $holdExpiresAt = Carbon::now('Asia/Colombo')->addMinutes(15);
            if ($vehicle && $estimate) {
                return response()->view('booking.airport-transfer.contact', [
                    'criteria' => $criteria,
                    'vehicle' => $vehicle,
                    'estimate' => $estimate,
                    'holdExpiresAt' => $holdExpiresAt,
                    'step' => 3,
                ]);
            }
        }

        if (! $criteria || ! $holdSession) {
            return redirect()->route('booking.airport-transfer')
                ->with('error', 'Checkout hold expired. Please reselect your vehicle.');
        }

        $vehicle = Vehicle::with(['media', 'publicApprovedMedia'])
            ->where('id', $holdSession['vehicle_id'])
            ->first();
        $estimate = $this->estimateService->findByReference($holdSession['estimate_reference']);

        if (! $vehicle || ! $estimate) {
            return redirect()->route('booking.airport-transfer')
                ->with('error', 'Selected vehicle or estimate expired. Please reselect your vehicle.');
        }

        $holdExpiresAt = Carbon::parse($holdSession['expires_at']);
        if ($holdExpiresAt->isPast()) {
            return redirect()->route('booking.airport-transfer.vehicles')
                ->with('error', '15-minute checkout hold has expired. Please choose a vehicle again.');
        }

        return response()->view('booking.airport-transfer.contact', [
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

        /** @var AirportTransferCriteria|null $criteria */
        $criteria = $request->session()->get('airport_transfer_criteria');
        $holdSession = $request->session()->get('airport_transfer_hold');

        if (! $criteria || ! $holdSession) {
            return redirect()->route('booking.airport-transfer')
                ->with('error', 'Checkout hold expired. Please reselect your vehicle.');
        }

        $leadTravelerSame = $request->boolean('lead_traveler_same');

        $submitData = new SubmitAirportTransferData(
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
            leadTravelerFirstName: $leadTravelerSame ? null : ($validated['lead_traveler_first_name'] ?? null),
            leadTravelerLastName: $leadTravelerSame ? null : ($validated['lead_traveler_last_name'] ?? null),
            leadTravelerPhone: $leadTravelerSame ? null : ($validated['lead_traveler_phone'] ?? null),
            leadTravelerIsWhatsapp: (bool) ($validated['lead_traveler_is_whatsapp'] ?? true),
            leadTravelerEmail: $validated['lead_traveler_email'] ?? null,
            termsAccepted: true,
            privacyAccepted: true,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
        );

        try {
            $result = $this->submissionService->submitAirportTransfer($submitData);
        } catch (ScheduleConflictException|InvalidArgumentException $e) {
            return redirect()->route('booking.airport-transfer.vehicles')
                ->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('booking.airport-transfer.vehicles')
                ->with('error', 'Unable to complete your advance request. Please reselect your vehicle.');
        }

        $request->session()->forget(['airport_transfer_criteria', 'airport_transfer_hold']);

        return redirect()->to($result->privateUrl);
    }
}
