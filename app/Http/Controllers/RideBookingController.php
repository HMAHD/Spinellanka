<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Availability\Exceptions\ScheduleConflictException;
use App\Domain\Availability\Services\AvailabilityService;
use App\Domain\Booking\DTOs\RideCriteria;
use App\Domain\Booking\DTOs\RideStopData;
use App\Domain\Booking\DTOs\SubmitRideData;
use App\Domain\Booking\Services\BookingSubmissionService;
use App\Domain\Booking\Services\RideSuitabilityService;
use App\Domain\Booking\ServiceType;
use App\Domain\Catalog\Enums\VehicleStatus;
use App\Domain\Pricing\DTOs\EstimateRequestData;
use App\Domain\Pricing\Services\EstimateService;
use App\Domain\Pricing\Services\PricingService;
use App\Models\Estimate;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Str;
use InvalidArgumentException;

class RideBookingController extends Controller
{
    public function __construct(
        protected RideSuitabilityService $suitabilityService,
        protected AvailabilityService $availabilityService,
        protected PricingService $pricingService,
        protected EstimateService $estimateService,
        protected BookingSubmissionService $submissionService,
    ) {}

    public function create(Request $request): Response
    {
        $criteria = $request->session()->get('ride_criteria');
        $draft = $request->session()->get('ride_draft');
        $dealPreset = $request->session()->get('booking_deal_preset');

        if ($criteria === null && is_array($dealPreset) && ($dealPreset['service_type'] ?? null) === ServiceType::Ride->value) {
            $travelDate = isset($dealPreset['travel_date']) ? (string) $dealPreset['travel_date'] : Carbon::now('Asia/Colombo')->addDays(2)->format('Y-m-d');
            $travelTime = isset($dealPreset['travel_time']) ? (string) $dealPreset['travel_time'] : '09:00';
            $pickupTime = Carbon::parse("{$travelDate} {$travelTime}", 'Asia/Colombo');

            $criteria = new RideCriteria(
                pickupTime: $pickupTime,
                originText: (string) ($dealPreset['origin'] ?? $dealPreset['origin_text'] ?? 'Colombo Fort'),
                destinationText: (string) ($dealPreset['destination'] ?? $dealPreset['destination_text'] ?? 'Kandy Temple'),
                passengerCount: (int) ($dealPreset['passenger_count'] ?? 2),
                luggageCount: (int) ($dealPreset['luggage_count'] ?? 2),
            );
        }

        return response()->view('booking.ride.create', [
            'criteria' => $criteria,
            'draft' => $draft,
            'step' => 1,
        ]);
    }

    public function search(Request $request): Response|RedirectResponse
    {
        $action = $request->input('action');

        // Progressive Enhancement: Handle No-JS Stop Modifications
        if ($action !== null && $action !== '' && $action !== 'search') {
            return $this->handleNoJsStopAction($request, (string) $action);
        }

        $validated = $request->validate([
            'origin_text' => ['required', 'string', 'max:500'],
            'destination_text' => ['required', 'string', 'max:500'],
            'pickup_date' => ['required', 'date_format:Y-m-d'],
            'pickup_time' => ['required', 'date_format:H:i'],
            'passenger_count' => ['required', 'integer', 'min:1', 'max:10'],
            'luggage_count' => ['required', 'integer', 'min:0', 'max:10'],
            'stops' => ['nullable', 'array', 'max:10'],
            'stops.*.location_text' => ['required_with:stops', 'string', 'max:500'],
            'stops.*.waiting_minutes' => ['required_with:stops', 'integer', 'min:0', 'max:300'],
            'has_return' => ['nullable', 'boolean'],
            'return_date' => ['required_if:has_return,1', 'nullable', 'date_format:Y-m-d'],
            'return_time' => ['required_if:has_return,1', 'nullable', 'date_format:H:i'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $pickupDateTime = Carbon::createFromFormat(
            'Y-m-d H:i',
            "{$validated['pickup_date']} {$validated['pickup_time']}",
            'Asia/Colombo'
        );

        if (! $pickupDateTime) {
            throw new InvalidArgumentException('Invalid pickup date and time.');
        }

        $minNoticeHours = (int) config('booking.ride.minimum_notice_hours', 6);
        if ($pickupDateTime->lt(Carbon::now('Asia/Colombo')->addHours($minNoticeHours))) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['pickup_time' => "Minimum notice of {$minNoticeHours} hours required before pickup."]);
        }

        // Validate cumulative planned waiting across all stops
        $rawStops = $validated['stops'] ?? [];
        $totalWaitingMinutes = 0;
        $stopsData = [];
        $order = 1;

        foreach ($rawStops as $s) {
            $locationText = trim((string) ($s['location_text'] ?? ''));
            if ($locationText === '') {
                continue;
            }
            $waiting = (int) ($s['waiting_minutes'] ?? 0);
            $totalWaitingMinutes += $waiting;

            $stopsData[] = new RideStopData(
                stopOrder: $order++,
                locationText: $locationText,
                plannedWaitingMinutes: $waiting,
            );
        }

        $maxCumulativeWaiting = (int) config('booking.ride.max_cumulative_waiting_minutes', 480);
        if ($totalWaitingMinutes > $maxCumulativeWaiting) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['stops' => "Cumulative planned stop waiting cannot exceed {$maxCumulativeWaiting} minutes (8 hours)."]);
        }

        $defaultLegDuration = (int) config('booking.ride.default_trip_duration_minutes', 180);
        $effectiveOutboundDuration = $defaultLegDuration + $totalWaitingMinutes;
        $outboundArrival = (clone $pickupDateTime)->addMinutes($effectiveOutboundDuration);

        $hasReturn = $request->boolean('has_return');
        $returnDateTime = null;

        if ($hasReturn) {
            if (empty($validated['return_date']) || empty($validated['return_time'])) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['return_time' => 'Please specify return pickup date and time.']);
            }

            $returnDateTime = Carbon::createFromFormat(
                'Y-m-d H:i',
                "{$validated['return_date']} {$validated['return_time']}",
                'Asia/Colombo'
            );

            if (! $returnDateTime) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['return_time' => 'Invalid return pickup date and time format.']);
            }

            $minReturnGapMinutes = (int) config('booking.ride.min_return_pickup_gap_minutes', 60);
            $earliestReturnTime = (clone $outboundArrival)->addMinutes($minReturnGapMinutes);

            if ($returnDateTime->lt($earliestReturnTime)) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors([
                        'return_time' => "Return pickup time must be at least {$minReturnGapMinutes} minutes after estimated outbound arrival ({$outboundArrival->format('H:i')}). Earliest allowed return pickup is {$earliestReturnTime->format('H:i')}.",
                    ]);
            }

            $maxReturnHorizonDays = (int) config('booking.ride.max_return_horizon_days', 30);
            if ($returnDateTime->gt((clone $pickupDateTime)->addDays($maxReturnHorizonDays))) {
                return redirect()->back()
                    ->withInput()
                    ->withErrors(['return_time' => "Return leg cannot be scheduled more than {$maxReturnHorizonDays} days from outbound pickup."]);
            }
        }

        $criteria = new RideCriteria(
            pickupTime: $pickupDateTime,
            originText: trim($validated['origin_text']),
            destinationText: trim($validated['destination_text']),
            passengerCount: (int) $validated['passenger_count'],
            luggageCount: (int) $validated['luggage_count'],
            stops: $stopsData,
            hasReturn: $hasReturn,
            returnPickupTime: $returnDateTime,
            specialInstructions: $validated['notes'] ?? null,
            outboundDurationMinutes: $defaultLegDuration,
            returnDurationMinutes: $hasReturn ? $defaultLegDuration : null,
        );

        $request->session()->forget('ride_draft');
        $request->session()->put('ride_criteria', $criteria);

        return redirect()->route('booking.ride.vehicles');
    }

    public function vehicles(Request $request): Response|RedirectResponse
    {
        /** @var RideCriteria|null $criteria */
        $criteria = $request->session()->get('ride_criteria');

        if (! $criteria && app()->environment('local', 'testing') && $request->has('preview')) {
            $pickup = Carbon::now('Asia/Colombo')->addDays(2)->setTime(9, 0);
            $criteria = new RideCriteria(
                pickupTime: $pickup,
                originText: 'Cinnamon Grand, Colombo 03',
                destinationText: 'Amangalla, Galle Fort',
                passengerCount: 2,
                luggageCount: 2,
                stops: [
                    new RideStopData(stopOrder: 1, locationText: 'Bentota Beach', plannedWaitingMinutes: 60),
                ],
                hasReturn: false,
            );
        }

        if (! $criteria) {
            return redirect()->route('booking.ride')
                ->with('error', 'Ride journey details expired. Please enter criteria again.');
        }

        $suitability = $this->suitabilityService->evaluate($criteria);

        return response()->view('booking.ride.vehicles', [
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

        /** @var RideCriteria|null $criteria */
        $criteria = $request->session()->get('ride_criteria');
        if (! $criteria) {
            return redirect()->route('booking.ride')
                ->with('error', 'Ride journey details expired. Please enter criteria again.');
        }

        $vehicle = Vehicle::where('id', $validated['vehicle_id'])->firstOrFail();

        if (! $vehicle->eligible_for_ride || $vehicle->status !== VehicleStatus::Active) {
            return redirect()->route('booking.ride.vehicles')
                ->with('error', 'Selected vehicle is not eligible for Ride service.');
        }

        // Release any existing checkout hold before creating a new one
        $previousHold = $request->session()->get('ride_hold');
        if ($previousHold) {
            if (! empty($previousHold['plain_token'])) {
                try {
                    $this->availabilityService->releaseHold($previousHold['plain_token'], 'vehicle_reselected');
                } catch (\Throwable) {
                }
            }
            if (! empty($previousHold['return_plain_token'])) {
                try {
                    $this->availabilityService->releaseHold($previousHold['return_plain_token'], 'vehicle_reselected');
                } catch (\Throwable) {
                }
            }
        }

        $startsAt = (clone $criteria->pickupTime)->setTimezone('Asia/Colombo');
        $outboundEndsAt = (clone $startsAt)->addMinutes($criteria->effectiveOutboundDurationMinutes());

        $groupId = $criteria->hasReturn ? (string) Str::uuid() : null;

        // 1. Create primary checkout hold (outbound leg)
        try {
            $outboundHoldResult = $this->availabilityService->createHold(
                vehicle: $vehicle,
                start: $startsAt,
                end: $outboundEndsAt,
                service: ServiceType::Ride,
                groupId: $groupId,
            );
        } catch (ScheduleConflictException $e) {
            return redirect()->route('booking.ride.vehicles')
                ->with('error', $e->getMessage());
        }

        // 2. Create return checkout hold if return leg requested and calculate estimate
        $returnHoldResult = null;
        try {
            if ($criteria->hasReturn && $criteria->returnPickupTime !== null) {
                $returnStartsAt = (clone $criteria->returnPickupTime)->setTimezone('Asia/Colombo');
                $returnEndsAt = (clone $returnStartsAt)->addMinutes($criteria->effectiveReturnDurationMinutes());

                $returnHoldResult = $this->availabilityService->createHold(
                    vehicle: $vehicle,
                    start: $returnStartsAt,
                    end: $returnEndsAt,
                    service: ServiceType::Ride,
                    groupId: $groupId,
                );
            }

            // 3. Calculate and snapshot pricing estimate
            $totalDistanceKm = ($criteria->outboundDistanceKm ?? 0.0) + ($criteria->returnDistanceKm ?? 0.0);
            $totalDurationMinutes = $criteria->effectiveOutboundDurationMinutes() + ($criteria->hasReturn ? $criteria->effectiveReturnDurationMinutes() : 0);

            $dealPreset = $request->session()->get('booking_deal_preset');
            $dealId = is_array($dealPreset) && isset($dealPreset['deal_id']) ? (int) $dealPreset['deal_id'] : null;

            $estimateRequest = new EstimateRequestData(
                serviceType: ServiceType::Ride,
                vehicleId: $vehicle->id,
                serviceDate: $startsAt,
                distanceKm: $totalDistanceKm > 0 ? $totalDistanceKm : null,
                durationHours: $totalDurationMinutes > 0 ? (float) ($totalDurationMinutes / 60) : null,
                stopCount: count($criteria->stops),
                waitingHours: (int) ceil($criteria->totalWaitingMinutes() / 60),
                extraIds: $criteria->extraIds,
                dealId: $dealId,
                assumptions: [
                    'origin' => $criteria->originText,
                    'destination' => $criteria->destinationText,
                    'has_return' => $criteria->hasReturn,
                    'stops_count' => count($criteria->stops),
                    'total_waiting_minutes' => $criteria->totalWaitingMinutes(),
                    'pickup_time' => $criteria->pickupTime->toIso8601String(),
                    'return_pickup_time' => $criteria->returnPickupTime?->toIso8601String(),
                ],
            );

            $estimateResult = $this->pricingService->calculateEstimate($estimateRequest);
            $estimate = $this->estimateService->snapshotEstimate($estimateResult, $estimateRequest);
        } catch (ScheduleConflictException $e) {
            // Roll back outbound hold atomically
            $this->availabilityService->releaseHold($outboundHoldResult->plainToken, 'return_hold_failed');

            return redirect()->route('booking.ride.vehicles')
                ->with('error', 'The vehicle is not available for the requested return time. Please choose another vehicle or time.');
        } catch (\Throwable $e) {
            // Release any acquired holds on unexpected failure
            $this->availabilityService->releaseHold($outboundHoldResult->plainToken, 'estimate_failed');
            if ($returnHoldResult) {
                $this->availabilityService->releaseHold($returnHoldResult->plainToken, 'estimate_failed');
            }

            report($e);

            return redirect()->route('booking.ride.vehicles')
                ->with('error', 'Unable to reserve vehicle or calculate pricing. Please try again.');
        }

        $request->session()->put('ride_hold', [
            'vehicle_id' => $vehicle->id,
            'plain_token' => $outboundHoldResult->plainToken,
            'return_plain_token' => $returnHoldResult?->plainToken,
            'hold_id' => $outboundHoldResult->hold->id,
            'expires_at' => $outboundHoldResult->hold->expires_at->toIso8601String(),
            'estimate_reference' => $estimate->reference,
        ]);

        return redirect()->route('booking.ride.contact');
    }

    public function contact(Request $request): Response|RedirectResponse
    {
        /** @var RideCriteria|null $criteria */
        $criteria = $request->session()->get('ride_criteria');
        $holdSession = $request->session()->get('ride_hold');

        if ((! $criteria || ! $holdSession) && app()->environment('local', 'testing') && $request->has('preview')) {
            $pickup = Carbon::now('Asia/Colombo')->addDays(2)->setTime(9, 0);
            $criteria = new RideCriteria(
                pickupTime: $pickup,
                originText: 'Cinnamon Grand, Colombo 03',
                destinationText: 'Amangalla, Galle Fort',
                passengerCount: 2,
                luggageCount: 2,
                stops: [
                    new RideStopData(stopOrder: 1, locationText: 'Bentota Beach', plannedWaitingMinutes: 60),
                ],
                hasReturn: false,
            );
            $vehicle = Vehicle::where('status', VehicleStatus::Active)
                ->where('eligible_for_ride', true)
                ->with(['media', 'publicApprovedMedia'])
                ->first();
            $estimate = Estimate::where('service_type', ServiceType::Ride)->latest()->first();
            $holdExpiresAt = Carbon::now('Asia/Colombo')->addMinutes(15);

            if ($vehicle && $estimate) {
                return response()->view('booking.ride.contact', [
                    'criteria' => $criteria,
                    'vehicle' => $vehicle,
                    'estimate' => $estimate,
                    'holdExpiresAt' => $holdExpiresAt,
                    'step' => 3,
                ]);
            }
        }

        if (! $criteria || ! $holdSession) {
            return redirect()->route('booking.ride')
                ->with('error', 'Checkout hold expired. Please reselect your vehicle.');
        }

        $vehicle = Vehicle::where('id', $holdSession['vehicle_id'])
            ->with(['media', 'publicApprovedMedia'])
            ->first();
        $estimate = $this->estimateService->findByReference($holdSession['estimate_reference']);

        if (! $vehicle || ! $estimate) {
            return redirect()->route('booking.ride')
                ->with('error', 'Selected vehicle or estimate expired. Please reselect your vehicle.');
        }

        $holdExpiresAt = Carbon::parse($holdSession['expires_at']);
        if ($holdExpiresAt->isPast()) {
            return redirect()->route('booking.ride.vehicles')
                ->with('error', '15-minute checkout hold has expired. Please choose a vehicle again.');
        }

        return response()->view('booking.ride.contact', [
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

        /** @var RideCriteria|null $criteria */
        $criteria = $request->session()->get('ride_criteria');
        $holdSession = $request->session()->get('ride_hold');

        if (! $criteria || ! $holdSession) {
            return redirect()->route('booking.ride')
                ->with('error', 'Checkout hold expired. Please reselect your vehicle.');
        }

        $holdExpiresAt = Carbon::parse($holdSession['expires_at']);
        if ($holdExpiresAt->isPast()) {
            return redirect()->route('booking.ride.vehicles')
                ->with('error', 'Checkout hold has expired. Please reselect your vehicle.');
        }

        $leadTravelerSame = $request->boolean('lead_traveler_same');

        $submitData = new SubmitRideData(
            criteria: $criteria,
            vehicleId: (int) $holdSession['vehicle_id'],
            estimateReference: (string) $holdSession['estimate_reference'],
            holdPlainToken: (string) $holdSession['plain_token'],
            bookerFirstName: $validated['booker_first_name'],
            bookerLastName: $validated['booker_last_name'],
            bookerPhone: $validated['booker_phone'],
            bookerIsWhatsapp: (bool) ($validated['booker_is_whatsapp'] ?? true),
            bookerEmail: $validated['booker_email'] ?? null,
            leadTravelerFirstName: $leadTravelerSame ? null : ($validated['lead_traveler_first_name'] ?? null),
            leadTravelerLastName: $leadTravelerSame ? null : ($validated['lead_traveler_last_name'] ?? null),
            leadTravelerPhone: $leadTravelerSame ? null : ($validated['lead_traveler_phone'] ?? null),
            leadTravelerIsWhatsapp: $leadTravelerSame ? null : (bool) ($validated['lead_traveler_is_whatsapp'] ?? true),
            leadTravelerEmail: $leadTravelerSame ? null : ($validated['lead_traveler_email'] ?? null),
            termsAccepted: true,
            privacyAccepted: true,
            marketingConsent: (bool) ($validated['booker_marketing_consent'] ?? false),
        );

        try {
            $result = $this->submissionService->submitRide($submitData);
        } catch (ScheduleConflictException|InvalidArgumentException $e) {
            return redirect()->route('booking.ride.vehicles')
                ->with('error', $e->getMessage());
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('booking.ride.vehicles')
                ->with('error', 'Unable to complete your advance request. Please reselect your vehicle.');
        }

        $request->session()->forget(['ride_criteria', 'ride_hold', 'ride_draft']);

        return redirect()->to($result->privateUrl);
    }

    /**
     * Progressive enhancement handler for No-JS stop modifications.
     */
    protected function handleNoJsStopAction(Request $request, string $action): RedirectResponse
    {
        $draft = $request->except(['_token', 'action']);
        $stops = $draft['stops'] ?? [];
        if (! is_array($stops)) {
            $stops = [];
        }

        // Re-index stops numerically
        $stops = array_values($stops);

        if ($action === 'add_stop') {
            if (count($stops) < 10) {
                $stops[] = [
                    'location_text' => '',
                    'waiting_minutes' => 0,
                ];
            }
        } elseif (str_starts_with($action, 'remove_stop_')) {
            $index = (int) substr($action, strlen('remove_stop_'));
            if (isset($stops[$index])) {
                unset($stops[$index]);
                $stops = array_values($stops);
            }
        } elseif (str_starts_with($action, 'move_up_')) {
            $index = (int) substr($action, strlen('move_up_'));
            if ($index > 0 && isset($stops[$index]) && isset($stops[$index - 1])) {
                $temp = $stops[$index - 1];
                $stops[$index - 1] = $stops[$index];
                $stops[$index] = $temp;
            }
        } elseif (str_starts_with($action, 'move_down_')) {
            $index = (int) substr($action, strlen('move_down_'));
            if ($index < count($stops) - 1 && isset($stops[$index]) && isset($stops[$index + 1])) {
                $temp = $stops[$index + 1];
                $stops[$index + 1] = $stops[$index];
                $stops[$index] = $temp;
            }
        }

        $draft['stops'] = $stops;
        $request->session()->put('ride_draft', $draft);

        return redirect()->route('booking.ride');
    }
}
