<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Booking\ServiceType;
use App\Domain\Offers\Services\DealCatalogService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class DealController extends Controller
{
    public function __construct(
        protected DealCatalogService $dealCatalogService
    ) {}

    /**
     * Display public catalog of curated deals with optional filters.
     */
    public function index(Request $request): Response
    {
        $serviceParam = $request->query('service');
        $serviceType = is_string($serviceParam) ? ServiceType::tryFrom($serviceParam) : null;
        $destination = $request->query('destination');
        $destinationStr = is_string($destination) ? $destination : null;

        $deals = $this->dealCatalogService->getPublishedDeals($serviceType, $destinationStr);

        return response()->view('deals.index', [
            'deals' => $deals,
            'selectedService' => $serviceType,
            'destination' => $destinationStr,
            'services' => ServiceType::cases(),
        ]);
    }

    /**
     * Display details for a single published deal.
     */
    public function show(string $slug): Response
    {
        $deal = $this->dealCatalogService->findPublishedBySlug($slug);

        if ($deal === null) {
            abort(404, 'The requested package deal is unavailable or has expired.');
        }

        return response()->view('deals.show', [
            'deal' => $deal,
        ]);
    }

    /**
     * Validate booking criteria and handoff to the appropriate service booking flow.
     */
    public function book(Request $request, string $slug): RedirectResponse
    {
        $deal = $this->dealCatalogService->findPublishedBySlug($slug);

        if ($deal === null) {
            abort(404, 'The requested package deal is unavailable or has expired.');
        }

        $validated = $request->validate([
            'travel_date' => ['required', 'date', 'after_or_equal:today'],
            'travel_time' => ['nullable', 'date_format:H:i'],
        ]);

        $preset = array_merge($deal->preset_payload, [
            'deal_id' => $deal->id,
            'deal_slug' => $deal->slug,
            'deal_title' => $deal->title,
            'service_type' => $deal->service_type->value,
            'travel_date' => $validated['travel_date'],
            'travel_time' => $validated['travel_time'] ?? '10:00',
        ]);

        $request->session()->put('booking_deal_preset', $preset);

        // Clear existing session criteria for the target service so fresh deal preset applies
        match ($deal->service_type) {
            ServiceType::AirportPickup => $request->session()->forget('airport_pickup_criteria'),
            ServiceType::AirportTransfer => $request->session()->forget('airport_transfer_criteria'),
            ServiceType::Ride => $request->session()->forget(['ride_criteria', 'ride_draft']),
            ServiceType::Rent => $request->session()->forget(['rent_criteria', 'rent_draft']),
        };

        return match ($deal->service_type) {
            ServiceType::AirportPickup => redirect()->route('booking.airport-pickup'),
            ServiceType::AirportTransfer => redirect()->route('booking.airport-transfer'),
            ServiceType::Ride => redirect()->route('booking.ride'),
            ServiceType::Rent => redirect()->route('booking.rent'),
        };
    }
}
