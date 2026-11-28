<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Availability\Exceptions\ScheduleConflictException;
use App\Domain\Availability\Services\AvailabilityService;
use App\Domain\Booking\DTOs\RentCriteria;
use App\Domain\Booking\DTOs\SubmitRentData;
use App\Domain\Booking\Services\BookingSubmissionService;
use App\Domain\Booking\Services\RentDurationCalculator;
use App\Domain\Booking\Services\RentSuitabilityService;
use App\Domain\Booking\ServiceType;
use App\Domain\Catalog\Enums\VehicleStatus;
use App\Domain\Pricing\DTOs\EstimateRequestData;
use App\Domain\Pricing\Services\EstimateService;
use App\Domain\Pricing\Services\PricingService;
use App\Models\Estimate;
use App\Models\RentPolicy;
use App\Models\Vehicle;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use InvalidArgumentException;

class RentBookingController extends Controller
{
    public function __construct(
        protected RentSuitabilityService $suitabilityService,
        protected RentDurationCalculator $durationCalculator,
        protected AvailabilityService $availabilityService,
        protected PricingService $pricingService,
        protected EstimateService $estimateService,
        protected BookingSubmissionService $submissionService,
    ) {}

    public function create(Request $request): Response
    {
        $criteria = $request->session()->get('rent_criteria');
        $dealPreset = $request->session()->get('booking_deal_preset');

        if ($criteria === null && is_array($dealPreset) && ($dealPreset['service_type'] ?? null) === ServiceType::Rent->value) {
            $travelDate = isset($dealPreset['travel_date']) ? (string) $dealPreset['travel_date'] : Carbon::now('Asia/Colombo')->addDays(2)->format('Y-m-d');
            $travelTime = isset($dealPreset['travel_time']) ? (string) $dealPreset['travel_time'] : '10:00';
            $durationDays = (int) ($dealPreset['duration_days'] ?? 3);
            $pickupDateTime = Carbon::parse("{$travelDate} {$travelTime}", 'Asia/Colombo');
            $returnDateTime = (clone $pickupDateTime)->addDays($durationDays);

            $criteria = new RentCriteria(
                pickupDateTime: $pickupDateTime,
                returnDateTime: $returnDateTime,
                pickupLocationType: (string) ($dealPreset['pickup_location_type'] ?? 'office'),
                pickupLocationText: (string) ($dealPreset['pickup_location_text'] ?? 'Colombo Central Hub'),
                returnLocationType: (string) ($dealPreset['return_location_type'] ?? 'office'),
                returnLocationText: (string) ($dealPreset['return_location_text'] ?? 'Colombo Central Hub'),
                driverAge: (int) ($dealPreset['driver_age'] ?? 30),
                driverLicenceCountry: (string) ($dealPreset['driver_licence_country'] ?? 'LK'),
                driverLicenceYears: (int) ($dealPreset['driver_licence_years'] ?? 5),
                hasIdp: (bool) ($dealPreset['has_idp'] ?? false),
                durationDays: $durationDays,
            );
        }

        $policy = RentPolicy::current();

        return response()->view('booking.rent.create', [
            'criteria' => $criteria,
            'policy' => $policy,
            'step' => 1,
        ]);
    }

    public function search(Request $request): Response|RedirectResponse
    {
        $validated = $request->validate([
            'pickup_date' => ['required', 'date_format:Y-m-d'],
            'pickup_time' => ['required', 'date_format:H:i'],
            'return_date' => ['required', 'date_format:Y-m-d'],
            'return_time' => ['required', 'date_format:H:i'],
            'pickup_location_type' => ['required', 'string', 'in:office,airport,hotel'],
            'pickup_location_text' => ['required', 'string', 'max:500'],
            'return_location_type' => ['required', 'string', 'in:office,airport,hotel'],
            'return_location_text' => ['required', 'string', 'max:500'],
            'driver_age' => ['required', 'integer', 'min:18', 'max:99'],
            'driver_licence_country' => ['required', 'string', 'max:100'],
            'driver_licence_years' => ['required', 'integer', 'min:0', 'max:70'],
            'has_idp' => ['nullable', 'boolean'],
            'has_additional_driver' => ['nullable', 'boolean'],
            'additional_driver_name' => ['nullable', 'string', 'max:255'],
            'additional_driver_age' => ['nullable', 'integer', 'min:18', 'max:99'],
            'additional_driver_licence_country' => ['nullable', 'string', 'max:100'],
            'additional_driver_licence_years' => ['nullable', 'integer', 'min:0', 'max:70'],
            'additional_driver_has_idp' => ['nullable', 'boolean'],
            'expected_distance_km' => ['nullable', 'integer', 'min:10', 'max:10000'],
            'special_instructions' => ['nullable', 'string', 'max:1000'],
        ]);

        $pickupDateTime = Carbon::createFromFormat(
            'Y-m-d H:i',
            "{$validated['pickup_date']} {$validated['pickup_time']}",
            'Asia/Colombo'
        );

        $returnDateTime = Carbon::createFromFormat(
            'Y-m-d H:i',
            "{$validated['return_date']} {$validated['return_time']}",
            'Asia/Colombo'
        );

        if (! $pickupDateTime || ! $returnDateTime) {
            throw new InvalidArgumentException('Invalid rental dates or times format.');
        }

        if ($returnDateTime->lte($pickupDateTime)) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['return_date' => 'Return date and time must be after scheduled pickup date and time.']);
        }

        $minNoticeHours = (int) config('booking.rent.minimum_notice_hours', 12);
        if ($pickupDateTime->lt(Carbon::now('Asia/Colombo')->addHours($minNoticeHours))) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['pickup_date' => "Minimum notice of {$minNoticeHours} hours required before vehicle handover."]);
        }

        $policy = RentPolicy::current();
        $durationResult = $this->durationCalculator->calculate($pickupDateTime, $returnDateTime, $policy);

        $hasIdp = $request->boolean('has_idp');
        $hasAdditional = $request->boolean('has_additional_driver');
        $additionalIdp = $request->boolean('additional_driver_has_idp');
        $deliveryCount = ($validated['pickup_location_type'] === 'hotel' ? 1 : 0) + ($validated['return_location_type'] === 'hotel' ? 1 : 0);

        $criteria = new RentCriteria(
            pickupDateTime: $pickupDateTime,
            returnDateTime: $returnDateTime,
            pickupLocationType: $validated['pickup_location_type'],
            pickupLocationText: trim($validated['pickup_location_text']),
            returnLocationType: $validated['return_location_type'],
            returnLocationText: trim($validated['return_location_text']),
            driverAge: (int) $validated['driver_age'],
            driverLicenceCountry: trim($validated['driver_licence_country']),
            driverLicenceYears: (int) $validated['driver_licence_years'],
            hasIdp: $hasIdp,
            durationDays: $durationResult->days,
            extraHours: $durationResult->extraHours,
            hasAdditionalDriver: $hasAdditional,
            additionalDriverName: $hasAdditional && ! empty($validated['additional_driver_name']) ? trim($validated['additional_driver_name']) : null,
            additionalDriverAge: $hasAdditional && ! empty($validated['additional_driver_age']) ? (int) $validated['additional_driver_age'] : null,
            additionalDriverLicenceCountry: $hasAdditional && ! empty($validated['additional_driver_licence_country']) ? trim($validated['additional_driver_licence_country']) : null,
            additionalDriverLicenceYears: $hasAdditional && ! empty($validated['additional_driver_licence_years']) ? (int) $validated['additional_driver_licence_years'] : null,
            additionalDriverHasIdp: $hasAdditional && $additionalIdp,
            expectedDistanceKm: ! empty($validated['expected_distance_km']) ? (int) $validated['expected_distance_km'] : null,
            deliveryCount: $deliveryCount,
            specialInstructions: ! empty($validated['special_instructions']) ? trim($validated['special_instructions']) : null,
        );

        $request->session()->put('rent_criteria', $criteria);

        return redirect()->route('booking.rent.vehicles');
    }

    public function vehicles(Request $request): Response|RedirectResponse
    {
        /** @var RentCriteria|null $criteria */
        $criteria = $request->session()->get('rent_criteria');

        if (! $criteria && app()->environment('local', 'testing') && $request->has('preview')) {
            $criteria = $this->createPreviewCriteria();
        }

        if (! $criteria) {
            return redirect()->route('booking.rent');
        }

        $policy = RentPolicy::current();
        $suitability = $this->suitabilityService->evaluate($criteria, $policy);

        return response()->view('booking.rent.vehicles', [
            'criteria' => $criteria,
            'suitability' => $suitability,
            'policy' => $policy,
            'step' => 2,
        ]);
    }

    public function hold(Request $request): RedirectResponse
    {
        /** @var RentCriteria|null $criteria */
        $criteria = $request->session()->get('rent_criteria');
        if (! $criteria) {
            return redirect()->route('booking.rent');
        }

        $validated = $request->validate([
            'vehicle_id' => ['required', 'integer'],
        ]);

        $vehicle = Vehicle::where('id', $validated['vehicle_id'])
            ->where('eligible_for_rent', true)
            ->where('status', VehicleStatus::Active)
            ->first();

        if (! $vehicle) {
            return redirect()->back()->with('error', 'The selected vehicle is not eligible or active for self-drive hire.');
        }

        // Release any existing hold before establishing a new one
        $previousHold = $request->session()->get('rent_hold');
        if ($previousHold && ! empty($previousHold['plain_token'])) {
            try {
                $this->availabilityService->releaseHold($previousHold['plain_token'], 'vehicle_reselected');
            } catch (\Throwable) {
                // Ignore failure on releasing expired or converted previous hold
            }
        }

        try {
            $holdResult = $this->availabilityService->createHold(
                vehicle: $vehicle,
                start: $criteria->pickupDateTime,
                end: $criteria->returnDateTime,
                service: ServiceType::Rent,
                sessionId: $request->session()->getId(),
                bufferBefore: 0,
                bufferAfter: $vehicle->turnaround_buffer_minutes,
            );

            // Create immutable Estimate snapshot
            $dealPreset = $request->session()->get('booking_deal_preset');
            $dealId = is_array($dealPreset) && isset($dealPreset['deal_id']) ? (int) $dealPreset['deal_id'] : null;

            $estimateRequest = new EstimateRequestData(
                serviceType: ServiceType::Rent,
                vehicleId: $vehicle->id,
                serviceDate: $criteria->pickupDateTime,
                rentalDays: $criteria->durationDays,
                durationHours: $criteria->extraHours > 0 ? (float) $criteria->extraHours : null,
                extraIds: $criteria->extraIds,
                dealId: $dealId,
                assumptions: [
                    'pickup_datetime' => $criteria->pickupDateTime->toIso8601String(),
                    'return_datetime' => $criteria->returnDateTime->toIso8601String(),
                    'duration_days' => $criteria->durationDays,
                    'extra_hours' => $criteria->extraHours,
                    'pickup_location' => $criteria->pickupLocationText,
                    'return_location' => $criteria->returnLocationText,
                ],
                sessionId: $request->session()->getId()
            );

            $estimateResult = $this->pricingService->calculateEstimate($estimateRequest);
            $estimate = $this->estimateService->snapshotEstimate($estimateResult, $estimateRequest);

            $request->session()->put('rent_hold', [
                'vehicle_id' => $vehicle->id,
                'plain_token' => $holdResult->plainToken,
                'expires_at' => $holdResult->hold->expires_at->toIso8601String(),
            ]);

            $request->session()->put('rent_estimate_reference', $estimate->reference);

            return redirect()->route('booking.rent.contact');
        } catch (ScheduleConflictException) {
            return redirect()->back()->with('error', 'This vehicle is no longer available for your requested rental dates. Please select another vehicle or choose alternative dates.');
        } catch (\Throwable $e) {
            report($e);

            return redirect()->back()->with('error', 'Unable to secure vehicle reservation hold. Please try again.');
        }
    }

    public function contact(Request $request): Response|RedirectResponse
    {
        /** @var RentCriteria|null $criteria */
        $criteria = $request->session()->get('rent_criteria');
        $holdData = $request->session()->get('rent_hold');
        $estimateReference = $request->session()->get('rent_estimate_reference');

        if ((! $criteria || ! $holdData || ! $estimateReference) && app()->environment('local', 'testing') && $request->has('preview')) {
            $criteria = $this->createPreviewCriteria();
            $firstVehicle = Vehicle::with(['media', 'publicApprovedMedia'])
                ->where('eligible_for_rent', true)
                ->where('status', VehicleStatus::Active)
                ->first();
            if ($firstVehicle) {
                $holdData = [
                    'vehicle_id' => $firstVehicle->id,
                    'plain_token' => 'preview_hold_token',
                    'expires_at' => Carbon::now('Asia/Colombo')->addMinutes(15)->toIso8601String(),
                ];
                $estimate = Estimate::where('service_type', ServiceType::Rent)->where('vehicle_id', $firstVehicle->id)->latest()->first();
                $estimateReference = $estimate?->reference;
            }
        }

        if (! $criteria || ! $holdData || ! $estimateReference) {
            return redirect()->route('booking.rent')->with('error', 'Your rental reservation session has expired. Please select your dates again.');
        }

        $expiresAt = Carbon::parse($holdData['expires_at']);
        if ($expiresAt->isPast()) {
            return redirect()->route('booking.rent.vehicles')->with('error', 'Your 15-minute checkout hold expired. Please re-select a vehicle.');
        }

        $vehicle = Vehicle::with(['media', 'publicApprovedMedia'])->findOrFail($holdData['vehicle_id']);
        $estimate = $this->estimateService->findByReference($estimateReference);
        $policy = RentPolicy::current();

        return response()->view('booking.rent.contact', [
            'criteria' => $criteria,
            'vehicle' => $vehicle,
            'estimate' => $estimate,
            'holdExpiresAt' => $expiresAt,
            'policy' => $policy,
            'step' => 3,
        ]);
    }

    public function submit(Request $request): RedirectResponse
    {
        /** @var RentCriteria|null $criteria */
        $criteria = $request->session()->get('rent_criteria');
        $holdData = $request->session()->get('rent_hold');
        $estimateReference = $request->session()->get('rent_estimate_reference');

        if (! $criteria || ! $holdData || ! $estimateReference) {
            return redirect()->route('booking.rent')->with('error', 'Your rental reservation session has expired. Please start again.');
        }

        $validated = $request->validate([
            'booker_first_name' => ['required', 'string', 'max:100'],
            'booker_last_name' => ['required', 'string', 'max:100'],
            'booker_phone' => ['required', 'string', 'max:50'],
            'booker_is_whatsapp' => ['nullable', 'boolean'],
            'booker_email' => ['nullable', 'email', 'max:255'],
            'booker_marketing_consent' => ['nullable', 'boolean'],
            'lead_driver_same_as_booker' => ['nullable', 'boolean'],
            'lead_driver_first_name' => ['nullable', 'string', 'max:100'],
            'lead_driver_last_name' => ['nullable', 'string', 'max:100'],
            'lead_driver_phone' => ['nullable', 'string', 'max:50'],
            'lead_driver_is_whatsapp' => ['nullable', 'boolean'],
            'lead_driver_email' => ['nullable', 'email', 'max:255'],
            'terms_accepted' => ['required', 'accepted'],
            'privacy_accepted' => ['required', 'accepted'],
            'original_documents_acknowledged' => ['required', 'accepted'],
        ]);

        $sameAsBooker = $request->boolean('lead_driver_same_as_booker');

        if (! $sameAsBooker && (empty($validated['lead_driver_first_name']) || empty($validated['lead_driver_phone']))) {
            return redirect()->back()
                ->withInput()
                ->withErrors(['lead_driver_first_name' => 'Please provide the lead driver name and contact number.']);
        }

        try {
            $submitData = new SubmitRentData(
                criteria: $criteria,
                vehicleId: (int) $holdData['vehicle_id'],
                holdPlainToken: $holdData['plain_token'],
                estimateReference: $estimateReference,
                bookerFirstName: trim($validated['booker_first_name']),
                bookerLastName: trim($validated['booker_last_name']),
                bookerPhone: trim($validated['booker_phone']),
                bookerIsWhatsapp: $request->boolean('booker_is_whatsapp'),
                bookerEmail: ! empty($validated['booker_email']) ? trim($validated['booker_email']) : null,
                bookerMarketingConsent: $request->boolean('booker_marketing_consent'),
                leadDriverSameAsBooker: $sameAsBooker,
                leadDriverFirstName: ! $sameAsBooker && ! empty($validated['lead_driver_first_name']) ? trim($validated['lead_driver_first_name']) : null,
                leadDriverLastName: ! $sameAsBooker && ! empty($validated['lead_driver_last_name']) ? trim($validated['lead_driver_last_name']) : null,
                leadDriverPhone: ! $sameAsBooker && ! empty($validated['lead_driver_phone']) ? trim($validated['lead_driver_phone']) : null,
                leadDriverIsWhatsapp: $request->boolean('lead_driver_is_whatsapp'),
                leadDriverEmail: ! $sameAsBooker && ! empty($validated['lead_driver_email']) ? trim($validated['lead_driver_email']) : null,
                termsAccepted: $request->boolean('terms_accepted'),
                privacyAccepted: $request->boolean('privacy_accepted'),
                originalDocumentsAcknowledged: $request->boolean('original_documents_acknowledged'),
                ipAddress: $request->ip(),
                userAgent: $request->userAgent()
            );

            $result = $this->submissionService->submitRent($submitData);

            // Clear session state
            $request->session()->forget(['rent_criteria', 'rent_hold', 'rent_estimate_reference']);

            return redirect()->to($result->privateUrl);
        } catch (\Throwable $e) {
            report($e);

            return redirect()->back()
                ->withInput()
                ->with('error', 'An error occurred while submitting your rental request: '.$e->getMessage());
        }
    }

    protected function createPreviewCriteria(): RentCriteria
    {
        $pickup = Carbon::now('Asia/Colombo')->addDays(2)->setTime(10, 0);
        $return = Carbon::now('Asia/Colombo')->addDays(5)->setTime(10, 0);

        return new RentCriteria(
            pickupDateTime: $pickup,
            returnDateTime: $return,
            pickupLocationType: 'airport',
            pickupLocationText: 'Colombo Bandaranaike International Airport (CMB)',
            returnLocationType: 'hotel',
            returnLocationText: 'Heritance Ahungalla, Galle Road',
            driverAge: 28,
            driverLicenceCountry: 'United Kingdom',
            driverLicenceYears: 6,
            hasIdp: true,
            durationDays: 3,
            extraHours: 0,
            hasAdditionalDriver: true,
            additionalDriverName: 'Jane Smith',
            additionalDriverAge: 27,
            additionalDriverLicenceCountry: 'United Kingdom',
            additionalDriverLicenceYears: 5,
            additionalDriverHasIdp: true,
            expectedDistanceKm: 450,
            deliveryCount: 1,
        );
    }
}
