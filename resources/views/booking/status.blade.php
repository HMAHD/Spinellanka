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
          Your Airport Pickup is Confirmed
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
                  <li>Complimentary 90-minute customs and baggage waiting time</li>
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
                <p>WhatsApp / Phone: {{ $booker->phone }}</p>
                @if($booker->email)
                  <p>Email: {{ $booker->email }}</p>
                @endif
              </div>
            @endif

            @if($leadTraveler && $leadTraveler->id !== $booker?->id)
              <div class="contact-box">
                <h4>Lead Traveler at CMB Airport</h4>
                <p><strong>{{ $leadTraveler->fullName() }}</strong></p>
                <p>Phone: {{ $leadTraveler->phone }}</p>
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
            <strong>USD ${{ number_format(($booking->total_amount_minor_units ?? 0) / 100, 2) }}</strong>
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
