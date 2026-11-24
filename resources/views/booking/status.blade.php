@extends('layouts.app')

@section('title', 'Request Under Review — '.$booking->reference.' — Spinellanka')
@section('meta_robots', 'noindex, nofollow')

@section('content')
<main id="main-content" class="booking-status-page">
  <div class="booking-container">
    <!-- Status Header Card -->
    <header class="status-hero-card" aria-labelledby="status-title">
      <div class="status-badge-row">
        @if($booking->status === \App\Domain\Booking\Enums\BookingStatus::Confirmed)
          <span class="status-pill status-confirmed">
            <span class="status-dot green"></span> Confirmed by Dispatch
          </span>
        @elseif($booking->status === \App\Domain\Booking\Enums\BookingStatus::Declined)
          <span class="status-pill status-declined">
            <span class="status-dot red"></span> Request Declined
          </span>
        @elseif($booking->status === \App\Domain\Booking\Enums\BookingStatus::Cancelled)
          <span class="status-pill status-cancelled">
            <span class="status-dot gray"></span> Cancelled
          </span>
        @elseif($booking->status === \App\Domain\Booking\Enums\BookingStatus::Expired)
          <span class="status-pill status-expired">
            <span class="status-dot gray"></span> Request Expired
          </span>
        @else
          <!-- Default for submitted / under_review: NEVER say confirmed -->
          <span class="status-pill status-pending">
            <span class="status-dot amber"></span> Request Received &bull; Under Review
          </span>
        @endif

        <span class="booking-ref-badge" aria-label="Booking reference number">
          Reference: <strong>{{ $booking->reference }}</strong>
        </span>
      </div>

      <h1 id="status-title">
        @if($booking->status === \App\Domain\Booking\Enums\BookingStatus::Confirmed)
          @if($booking->service_type === \App\Domain\Booking\ServiceType::Ride)
            Your Chauffeured Ride is Confirmed
          @elseif($booking->service_type === \App\Domain\Booking\ServiceType::AirportTransfer)
            Your Airport Transfer is Confirmed
          @else
            Your Airport Pickup is Confirmed
          @endif
        @elseif($booking->status === \App\Domain\Booking\Enums\BookingStatus::Declined)
          Reservation Request Declined
        @else
          Request Received — Under Review
        @endif
      </h1>

      <p class="status-lead">
        @if($booking->status === \App\Domain\Booking\Enums\BookingStatus::Confirmed)
          Our dispatch team has verified your flight arrival schedule and assigned your dedicated driver.
        @elseif($booking->status === \App\Domain\Booking\Enums\BookingStatus::Declined)
          {{ $booking->decline_reason ?? 'Unfortunately, we are unable to accommodate this schedule.' }}
        @else
          Thank you for choosing Spinellanka. Your advance airport pickup request is currently being reviewed by our operations desk. We track flight schedule changes and driver turnaround buffers before manual confirmation. No payment has been processed.
        @endif
      </p>

      <div class="private-link-notice" role="note">
        <svg class="lock-icon" aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
          <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
        </svg>
        <span>This private status page is secured with your personal access token. Bookmark this URL to check your status at any time.</span>
      </div>
    </header>

    <div class="booking-grid">
      <!-- Left Column: Flight & Vehicle Details -->
      <div class="booking-main-col">
        @if($booking->service_type === \App\Domain\Booking\ServiceType::Ride && isset($rideDetail) && $rideDetail)
          <!-- Point-to-Point / Multi-Stop Chauffeured Ride Details -->
          <section class="booking-card" aria-labelledby="ride-details-heading">
            <h2 id="ride-details-heading" class="card-heading">Ride Itinerary &amp; Journey Details</h2>
            <dl class="details-grid">
              <div>
                <dt>Pickup Origin</dt>
                <dd><strong>{{ $rideDetail->origin_text }}</strong></dd>
              </div>
              <div>
                <dt>Pickup Date &amp; Time</dt>
                <dd>
                  <strong>{{ $rideDetail->pickup_time?->format('D, d M Y @ H:i') }}</strong>
                  <small class="tz-badge">Sri Lanka Time (Asia/Colombo / UTC+05:30)</small>
                </dd>
              </div>
              <div>
                <dt>Final Destination</dt>
                <dd><strong>{{ $rideDetail->destination_text }}</strong></dd>
              </div>
              <div>
                <dt>Travel Party</dt>
                <dd>{{ $rideDetail->passenger_count }} Passengers &bull; {{ $rideDetail->luggage_count }} Checked Luggage Bags</dd>
              </div>
              @if($rideDetail->stops && $rideDetail->stops->isNotEmpty())
                <div style="grid-column: 1 / -1;">
                  <dt>Scheduled En-Route Stops ({{ $rideDetail->stops->count() }})</dt>
                  <dd>
                    <ol class="ride-stops-summary-list" style="margin: 0.5rem 0 0 1.25rem; padding: 0;">
                      @foreach($rideDetail->stops->sortBy('stop_order') as $st)
                        <li style="margin-bottom: 0.35rem;">
                          <strong>{{ $st->location_text }}</strong>
                          @if($st->planned_waiting_minutes > 0)
                            <span class="wait-tag" style="display: inline-block; margin-left: 0.5rem; font-size: 0.8rem; background: var(--color-surface-sunken, #f1f5f9); padding: 0.15rem 0.4rem; border-radius: 4px;">
                              ⏱ {{ $st->planned_waiting_minutes }} min planned waiting
                            </span>
                          @endif
                        </li>
                      @endforeach
                    </ol>
                  </dd>
                </div>
              @endif
              @if($rideDetail->has_return)
                <div style="grid-column: 1 / -1;">
                  <dt>Return Journey Leg</dt>
                  <dd>
                    <strong>Scheduled Return Pickup: {{ $rideDetail->return_pickup_time?->format('D, d M Y @ H:i') }}</strong>
                    <p style="margin: 0.25rem 0 0; font-size: 0.875rem; color: var(--color-text-secondary, #64748b);">
                      Route: {{ $rideDetail->return_origin_text ?: $rideDetail->destination_text }} &rarr; {{ $rideDetail->return_destination_text ?: $rideDetail->origin_text }} (Same exact vehicle and dedicated chauffeur).
                    </p>
                  </dd>
                </div>
              @endif
            </dl>

            <!-- Phase 12 Presentation Boundary: Route Map Container with Zero Geolocation & Accessible Text Itinerary Fallback -->
            <div id="ride-route-map"
                 class="ride-route-map-boundary"
                 data-origin="{{ $rideDetail->origin_text }}"
                 data-destination="{{ $rideDetail->destination_text }}"
                 data-has-return="{{ $rideDetail->has_return ? '1' : '0' }}"
                 data-stops='@json($rideDetail->stops->map(fn($s) => ["order" => $s->stop_order, "location" => $s->location_text, "waiting" => $s->planned_waiting_minutes]))'
                 style="margin-top: 1.5rem; padding: 1rem; border: 1px solid var(--color-border, #e2e8f0); border-radius: 8px; background: var(--color-surface, #ffffff);"
                 aria-label="Route Itinerary Overview">
              <div class="map-placeholder-header" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;">
                <span style="font-weight: 600; font-size: 0.925rem; display: flex; align-items: center; gap: 0.5rem;">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                  Journey Route Overview
                </span>
                <span class="route-badge" style="font-size: 0.75rem; padding: 0.2rem 0.5rem; background: var(--color-brand-surface, #e0f2fe); color: var(--color-brand, #0284c7); border-radius: 4px; font-weight: 500;">
                  Chauffeured Itinerary
                </span>
              </div>
              <!-- Accessible Text Itinerary Fallback (survives without JS / map provider) -->
              <div class="route-text-itinerary" aria-label="Step-by-step route itinerary">
                <div class="itinerary-stop" style="display: flex; gap: 0.75rem; align-items: flex-start; margin-bottom: 0.5rem;">
                  <span style="font-weight: bold; color: var(--color-brand, #0284c7);">A.</span>
                  <div>
                    <strong>Origin:</strong> {{ $rideDetail->origin_text }}
                  </div>
                </div>
                @foreach($rideDetail->stops->sortBy('stop_order') as $st)
                  <div class="itinerary-stop" style="display: flex; gap: 0.75rem; align-items: flex-start; margin-bottom: 0.5rem; padding-left: 0.5rem;">
                    <span style="font-weight: bold; color: var(--color-text-secondary, #64748b);">{{ chr(65 + $loop->iteration) }}.</span>
                    <div>
                      <strong>En-Route Stop {{ $st->stop_order }}:</strong> {{ $st->location_text }}
                      @if($st->planned_waiting_minutes > 0)
                        <span style="font-size: 0.8rem; color: var(--color-text-muted, #94a3b8);">({{ $st->planned_waiting_minutes }} min waiting)</span>
                      @endif
                    </div>
                  </div>
                @endforeach
                <div class="itinerary-stop" style="display: flex; gap: 0.75rem; align-items: flex-start; margin-bottom: 0.5rem;">
                  <span style="font-weight: bold; color: var(--color-brand, #0284c7);">{{ chr(65 + 1 + $rideDetail->stops->count()) }}.</span>
                  <div>
                    <strong>Destination:</strong> {{ $rideDetail->destination_text }}
                  </div>
                </div>
                @if($rideDetail->has_return)
                  <div class="itinerary-stop" style="display: flex; gap: 0.75rem; align-items: flex-start; margin-top: 0.75rem; padding-top: 0.5rem; border-top: 1px dashed var(--color-border, #e2e8f0);">
                    <span style="font-weight: bold; color: var(--color-success, #16a34a);">&hookleftarrow;</span>
                    <div>
                      <strong>Return Leg:</strong> {{ $rideDetail->destination_text }} &rarr; {{ $rideDetail->origin_text }}
                      <span style="display: block; font-size: 0.8rem; color: var(--color-text-muted, #94a3b8);">Scheduled: {{ $rideDetail->return_pickup_time?->format('D, d M Y @ H:i') }}</span>
                    </div>
                  </div>
                @endif
              </div>
              <p style="margin: 0.75rem 0 0; font-size: 0.75rem; color: var(--color-text-muted, #94a3b8);">
                * Route preview is illustrative. Chauffeur selects the optimal expressway route based on real-time traffic and highway conditions. Live dispatch tracking is not active during the request review stage.
              </p>
            </div>
          </section>
        @elseif($booking->service_type === \App\Domain\Booking\ServiceType::AirportTransfer && isset($transferDetail) && $transferDetail)
          <!-- Flight Departure & Transfer Details -->
          <section class="booking-card" aria-labelledby="transfer-details-heading">
            <h2 id="transfer-details-heading" class="card-heading">Departure Flight &amp; Transfer Details</h2>
            <dl class="details-grid">
              <div>
                <dt>Departure Airport</dt>
                <dd>Bandaranaike International Airport (CMB), Katunayake</dd>
              </div>
              <div>
                <dt>Flight Number</dt>
                <dd><strong>{{ $transferDetail->flight_number }}</strong></dd>
              </div>
              <div>
                <dt>Scheduled Departure</dt>
                <dd>
                  <strong>{{ $transferDetail->departure_time?->format('D, d M Y @ H:i') }}</strong>
                  <small class="tz-badge">Sri Lanka Time (Asia/Colombo / UTC+05:30)</small>
                </dd>
              </div>
              <div>
                <dt>Pickup Origin</dt>
                <dd>
                  <strong>{{ $transferDetail->origin_text }}</strong>
                  @if($transferDetail->origin_notes)
                    <p class="landmark-note"><em>Notes: {{ $transferDetail->origin_notes }}</em></p>
                  @endif
                </dd>
              </div>
              <div>
                <dt>Pickup Timing</dt>
                <dd>
                  <strong>{{ $transferDetail->chosen_pickup_time?->format('D, d M Y @ H:i') }}</strong>
                  @if($transferDetail->is_unsafe_time)
                    <span class="warning-tag" style="display:block; margin-top: 0.25rem;">⚠️ Later than Recommended (Client Risk Acknowledged)</span>
                  @else
                    <span class="safe-tag" style="display:block; margin-top: 0.25rem;">✓ Recommended Safe Pickup Time</span>
                  @endif
                </dd>
              </div>
              <div>
                <dt>Travel Party</dt>
                <dd>{{ $transferDetail->passenger_count }} Passengers &bull; {{ $transferDetail->luggage_count }} Checked Luggage Bags</dd>
              </div>
            </dl>
          </section>
        @else
          <!-- Flight & Arrival Details -->
          <section class="booking-card" aria-labelledby="flight-details-heading">
            <h2 id="flight-details-heading" class="card-heading">Flight &amp; Arrival Details</h2>
            <dl class="details-grid">
              <div>
                <dt>Airport</dt>
                <dd>Bandaranaike International Airport (CMB), Katunayake</dd>
              </div>
              <div>
                <dt>Flight Number</dt>
                <dd><strong>{{ $detail?->flight_number }}</strong></dd>
              </div>
              <div>
                <dt>Scheduled Arrival</dt>
                <dd>
                  <strong>{{ $detail?->arrival_time?->format('D, d M Y @ H:i') }}</strong>
                  <small class="tz-badge">Sri Lanka Time (Asia/Colombo / UTC+05:30)</small>
                </dd>
              </div>
              <div>
                <dt>Drop-off Destination</dt>
                <dd>
                  <strong>{{ $detail?->destination_text }}</strong>
                  @if($detail?->destination_notes)
                    <p class="landmark-note"><em>Notes: {{ $detail->destination_notes }}</em></p>
                  @endif
                </dd>
              </div>
              <div>
                <dt>Travel Party</dt>
                <dd>{{ $detail?->passenger_count }} Passengers &bull; {{ $detail?->luggage_count }} Checked Luggage Bags</dd>
              </div>
              <div>
                <dt>Meet &amp; Greet</dt>
                <dd>
                  {{ $detail?->meet_and_greet ? 'Included with Welcome Board' : 'Standard Pickup' }}
                  @if($detail?->name_board_text)
                    <span class="nameboard-tag">Name Board: &ldquo;{{ $detail->name_board_text }}&rdquo;</span>
                  @endif
                </dd>
              </div>
            </dl>
          </section>
        @endif

        <!-- Vehicle Allocation Card -->
        @if($booking->vehicle)
          <section class="booking-card" aria-labelledby="vehicle-details-heading">
            <h2 id="vehicle-details-heading" class="card-heading">Reserved Exact Vehicle</h2>
            <div class="vehicle-summary-row">
              <div class="vehicle-meta">
                <h3>{{ $booking->vehicle->make }} {{ $booking->vehicle->model }} ({{ $booking->vehicle->year }})</h3>
                <p class="vehicle-specs">
                  Type: {{ $booking->vehicle->vehicle_type->getLabel() }} &bull; Plate: <strong>{{ $booking->vehicle->license_plate }}</strong>
                </p>
                <ul class="vehicle-perks">
                  <li>Chauffeured English-speaking driver included</li>
                  <li>{{ $booking->vehicle->has_ac ? 'Air conditioned' : 'Non-AC' }}</li>
                  @if($booking->service_type === \App\Domain\Booking\ServiceType::Ride)
                    <li>Southern / Central expressway tolls included in base rate</li>
                    <li>45-minute courtesy waiting time at origin included</li>
                  @elseif($booking->service_type === \App\Domain\Booking\ServiceType::AirportTransfer)
                    <li>Katunayake Expressway tolls and airport departure drop-off fees included</li>
                  @else
                    <li>Complimentary 90-minute customs and baggage waiting time</li>
                  @endif
                </ul>
              </div>
            </div>
          </section>
        @endif

        <!-- Contacts Card -->
        <section class="booking-card" aria-labelledby="contacts-heading">
          <h2 id="contacts-heading" class="card-heading">Contact Information</h2>
          <div class="contacts-grid">
            @if($booker)
              <div class="contact-box">
                <h4>Booker (Primary Contact)</h4>
                <p><strong>{{ $booker->fullName() }}</strong></p>
                <p>WhatsApp / Phone: {{ strlen($booker->phone) > 6 ? substr($booker->phone, 0, 4) . ' ••••• ' . substr($booker->phone, -3) : $booker->phone }}</p>
                @if($booker->email)
                  <p>Email: {{ str_contains($booker->email, '@') ? substr($booker->email, 0, 1) . '•••••@' . explode('@', $booker->email)[1] : $booker->email }}</p>
                @endif
              </div>
            @endif

            @if($leadTraveler && $leadTraveler->id !== $booker?->id)
              <div class="contact-box">
                <h4>Lead Traveler at CMB Airport</h4>
                <p><strong>{{ $leadTraveler->fullName() }}</strong></p>
                <p>Phone: {{ strlen($leadTraveler->phone) > 6 ? substr($leadTraveler->phone, 0, 4) . ' ••••• ' . substr($leadTraveler->phone, -3) : $leadTraveler->phone }}</p>
              </div>
            @endif
          </div>
        </section>
      </div>

      <!-- Right Column: Estimate Snapshot & Dispatch Process -->
      <aside class="booking-side-col">
        <!-- Pricing Estimate Card -->
        <div class="booking-card pricing-card" aria-labelledby="estimate-heading">
          <h2 id="estimate-heading" class="card-heading">Estimate Breakdown</h2>

          @if($booking->estimate && $booking->estimate->items->isNotEmpty())
            <ul class="breakdown-list">
              @foreach($booking->estimate->items as $item)
                <li>
                  <span>{{ $item->description }}</span>
                  <span>${{ number_format($item->total_amount_minor_units / 100, 2) }}</span>
                </li>
              @endforeach
            </ul>
          @endif

          <div class="total-row">
            <span>Total Estimated Fee:</span>
            @if($booking->total_amount_minor_units !== null)
              <strong>USD ${{ number_format($booking->total_amount_minor_units / 100, 2) }}</strong>
            @else
              <strong>Custom Quote</strong>
            @endif
          </div>

          @if($booking->approximate_lkr_amount_minor_units)
            <div class="lkr-basis-box">
              <span class="lkr-label">Approximate LKR Basis:</span>
              <span class="lkr-val">LKR {{ number_format($booking->approximate_lkr_amount_minor_units / 100, 0) }}*</span>
              <small class="lkr-disclaimer">*Indicative rate for local reference. Final settlement amount is in USD.</small>
            </div>
          @endif

          <div class="payment-policy-note">
            <small><strong>Payment timing:</strong> No advance payment is charged today. Payment is arranged upon manual confirmation or vehicle dispatch.</small>
          </div>
        </div>

        <!-- Next Steps Card -->
        <div class="booking-card next-steps-card" aria-labelledby="dispatch-steps-heading">
          <h2 id="dispatch-steps-heading" class="card-heading">What Happens Next?</h2>
          <ol class="dispatch-steps-list">
            <li>
              <strong>Flight Dispatch Review:</strong>
              <p>Our team verifies driver availability, airport gate logistics, and flight schedule.</p>
            </li>
            <li>
              <strong>Direct WhatsApp Outreach:</strong>
              <p>We message your provided international number to confirm arrival details and driver contact.</p>
            </li>
            <li>
              <strong>Terminal Meet &amp; Greet:</strong>
              <p>Your driver waits inside the arrivals hall holding your name board.</p>
            </li>
          </ol>

          <div class="support-action-box">
            <p>Need to update your flight or arrival time?</p>
            <a href="{{ route('contact') }}" class="support-btn">Contact Dispatch Support</a>
          </div>
        </div>
      </aside>
    </div>
  </div>
</main>
@endsection
