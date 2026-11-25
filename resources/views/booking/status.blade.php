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
          @if($booking->service_type === \App\Domain\Booking\ServiceType::Rent)
            Your Self-Drive Vehicle Hire is Confirmed
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
          @if($booking->service_type === \App\Domain\Booking\ServiceType::Rent)
            Our dispatch desk has confirmed vehicle preparation and allocated your reserved exact vehicle for handover.
          @else
            Our dispatch team has verified your flight schedule and assigned your dedicated driver.
          @endif
        @elseif($booking->status === \App\Domain\Booking\Enums\BookingStatus::Declined)
          {{ $booking->decline_reason ?? 'Unfortunately, we are unable to accommodate this schedule.' }}
        @else
          @if($booking->service_type === \App\Domain\Booking\ServiceType::Rent)
            Thank you for choosing Spinellanka. Your advance self-drive vehicle hire request is currently being reviewed by our operations desk. We verify vehicle preparation and turnaround buffers before manual confirmation. No payment has been processed.
          @else
            Thank you for choosing Spinellanka. Your advance transport request is currently being reviewed by our operations desk. We track flight schedule changes and driver turnaround buffers before manual confirmation. No payment has been processed.
          @endif
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
        @if($booking->service_type === \App\Domain\Booking\ServiceType::Rent && isset($rentDetail) && $rentDetail)
          <!-- Rent (Self-Drive) Details -->
          <section class="booking-card" aria-labelledby="rent-details-heading">
            <h2 id="rent-details-heading" class="card-heading">Self-Drive Rental &amp; Handover Details</h2>
            <dl class="details-grid">
              <div>
                <dt>Pickup Handover Date &amp; Time</dt>
                <dd>
                  <strong>{{ $rentDetail->pickup_datetime?->format('D, d M Y @ H:i') }}</strong>
                  <small class="tz-badge">Sri Lanka Time (Asia/Colombo / UTC+05:30)</small>
                </dd>
              </div>
              <div>
                <dt>Return Collection Date &amp; Time</dt>
                <dd>
                  <strong>{{ $rentDetail->return_datetime?->format('D, d M Y @ H:i') }}</strong>
                  <small class="tz-badge">Sri Lanka Time (Asia/Colombo / UTC+05:30)</small>
                </dd>
              </div>
              <div>
                <dt>Handover Location (Pickup)</dt>
                <dd>
                  <strong>{{ $rentDetail->pickup_location_text }}</strong>
                  <span class="safe-tag" style="display:inline-block; margin-top: 0.25rem;">{{ ucfirst($rentDetail->pickup_location_type) }} Handover</span>
                </dd>
              </div>
              <div>
                <dt>Collection Location (Return)</dt>
                <dd>
                  <strong>{{ $rentDetail->return_location_text }}</strong>
                  <span class="safe-tag" style="display:inline-block; margin-top: 0.25rem;">{{ ucfirst($rentDetail->return_location_type) }} Return</span>
                </dd>
              </div>
              <div>
                <dt>Rental Duration</dt>
                <dd>
                  <strong>{{ $rentDetail->duration_days }} Days{{ $rentDetail->extra_hours > 0 ? ' + '.$rentDetail->extra_hours.' Extra Hours' : '' }}</strong>
                </dd>
              </div>
              <div>
                <dt>Mileage Allowance</dt>
                <dd>{{ $rentDetail->included_distance_km }} km included cumulative (excess billed at return)</dd>
              </div>
              <div>
                <dt>Security Deposit</dt>
                <dd><strong>USD ${{ number_format($rentDetail->security_deposit_minor_units / 100, 2) }}</strong> (Refundable pre-auth)</dd>
              </div>
              <div>
                <dt>Insurance Coverage</dt>
                <dd>Comprehensive included (Max Excess: USD ${{ number_format($rentDetail->insurance_excess_minor_units / 100, 2) }})</dd>
              </div>
              <div>
                <dt>Lead Driver Eligibility</dt>
                <dd>Age: {{ $rentDetail->driver_age }} &bull; Licence: {{ $rentDetail->driver_licence_country }} ({{ $rentDetail->driver_licence_years }} yrs held) &bull; IDP: {{ $rentDetail->has_idp ? 'Yes' : 'No' }}</dd>
              </div>
              <div>
                <dt>Additional Driver</dt>
                <dd>{{ $rentDetail->has_additional_driver ? $rentDetail->additional_driver_name.' (Age: '.$rentDetail->additional_driver_age.')' : 'None registered' }}</dd>
              </div>
            </dl>

            <div class="alert alert-info" style="margin-top: 1rem; border-left: 4px solid var(--color-primary, #0f172a); background: rgba(15, 23, 42, 0.04); padding: 0.75rem 1rem; border-radius: 4px;">
              <strong>Mandatory In-Person Document Inspection at Handover:</strong>
              <p style="margin: 0.25rem 0 0; font-size: 0.875rem;">Under Sri Lankan law and insurance requirements, the lead driver must present original physical passport with valid visa, home country national driving licence, and International Driving Permit (IDP) or AAC endorsement before the vehicle can be released. Digital copies or photographs cannot be accepted.</p>
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
                  @if($booking->service_type === \App\Domain\Booking\ServiceType::Rent)
                    <li>Self-drive vehicle hire (no driver assigned)</li>
                    <li>{{ $booking->vehicle->has_ac ? 'Air conditioned' : 'Non-AC' }}</li>
                    <li>{{ $booking->rentDetail ? ($booking->rentDetail->duration_days > 0 ? (int) round($booking->rentDetail->included_distance_km / $booking->rentDetail->duration_days) : 100) : 100 }} km/day cumulative mileage included</li>
                    <li>USD ${{ number_format(($booking->rentDetail ? $booking->rentDetail->security_deposit_minor_units : 50000) / 100, 2) }} refundable security deposit pre-authorization</li>
                  @else
                    <li>Chauffeured English-speaking driver included</li>
                    <li>{{ $booking->vehicle->has_ac ? 'Air conditioned' : 'Non-AC' }}</li>
                    @if($booking->service_type === \App\Domain\Booking\ServiceType::AirportTransfer)
                      <li>Katunayake Expressway tolls and airport departure drop-off fees included</li>
                    @else
                      <li>Complimentary 90-minute customs and baggage waiting time</li>
                    @endif
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
            @if($booking->service_type === \App\Domain\Booking\ServiceType::Rent)
              <li>
                <strong>Operations Logistics Review:</strong>
                <p>Our team verifies vehicle maintenance, cleaning, turnaround buffers, and handover readiness.</p>
              </li>
              <li>
                <strong>Direct WhatsApp Coordination:</strong>
                <p>We contact your international WhatsApp number to confirm exact handover timing and location instructions.</p>
              </li>
              <li>
                <strong>Vehicle Handover &amp; Document Inspection:</strong>
                <p>In-person verification of physical original passport, home licence, and IDP prior to vehicle release.</p>
              </li>
            @else
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
            @endif
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
