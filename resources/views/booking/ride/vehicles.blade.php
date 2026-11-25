@extends('layouts.app')

@section('title', 'Select Vehicle — Chauffeured Ride — Spinellanka')
@section('meta_description', 'Select your exact chauffeured vehicle for your ride across Sri Lanka.')

@section('content')
<main id="main-content" class="booking-page">
  <div class="booking-container">
    <div class="booking-header">
      <nav class="booking-breadcrumbs" aria-label="Breadcrumb">
        <a href="{{ route('home') }}">Home</a> &rsaquo;
        <a href="{{ route('booking.ride') }}">Ride Details</a> &rsaquo;
        <span aria-current="page">Select Vehicle</span>
      </nav>
      <h1 id="page-title">Select Your Chauffeured Vehicle</h1>
      <p class="booking-subtitle">Choose the exact vehicle for your journey. An exclusive 15-minute checkout hold is established upon selection.</p>

      <ol class="booking-steps" aria-label="Booking progress">
        <li class="step completed"><span class="step-num">&#10003;</span> <span class="step-title">Route &amp; Timing</span></li>
        <li class="step active" aria-current="step"><span class="step-num">2</span> <span class="step-title">Select Vehicle</span></li>
        <li class="step"><span class="step-num">3</span> <span class="step-title">Traveler Details</span></li>
        <li class="step"><span class="step-num">4</span> <span class="step-title">Review</span></li>
      </ol>
    </div>

    <!-- Criteria Summary Banner -->
    <section class="criteria-summary-banner" aria-label="Selected journey itinerary criteria">
      <div class="criteria-summary-content">
        <div class="summary-pill">
          <span class="pill-label">Origin</span>
          <span class="pill-value">{{ Str::limit($criteria->originText, 25) }}</span>
        </div>
        <div class="summary-pill">
          <span class="pill-label">Destination</span>
          <span class="pill-value">{{ Str::limit($criteria->destinationText, 25) }}</span>
        </div>
        <div class="summary-pill">
          <span class="pill-label">Pickup Time</span>
          <span class="pill-value">{{ $criteria->pickupTime->format('D, d M Y @ H:i') }} <small>(Asia/Colombo)</small></span>
        </div>
        <div class="summary-pill">
          <span class="pill-label">Party</span>
          <span class="pill-value">{{ $criteria->passengerCount }} Pass &bull; {{ $criteria->luggageCount }} Bags</span>
        </div>
        @if(!empty($criteria->stops))
          <div class="summary-pill">
            <span class="pill-label">Stops</span>
            <span class="pill-value">{{ count($criteria->stops) }} en-route ({{ $criteria->totalWaitingMinutes() }} min wait)</span>
          </div>
        @endif
        @if($criteria->hasReturn)
          <div class="summary-pill">
            <span class="pill-label">Return Leg</span>
            <span class="pill-value">{{ $criteria->returnPickupTime?->format('D, d M @ H:i') }}</span>
          </div>
        @endif
      </div>
      <a href="{{ route('booking.ride') }}" class="edit-criteria-link">Edit journey criteria</a>
    </section>

    @if(session('error'))
      <div class="alert alert-error" role="alert">
        {{ session('error') }}
      </div>
    @endif

    <section class="vehicles-selection-section" aria-labelledby="suitable-vehicles-heading">
      <h2 id="suitable-vehicles-heading" class="section-title">Available Exact Vehicles ({{ $suitable->count() }})</h2>
      <p class="section-desc">Every vehicle shown is verified for passenger capacity, luggage volume, and has an active dedicated chauffeur assigned for your schedule.</p>

      @if($suitable->isEmpty())
        <div class="empty-results-card">
          <h3>No Vehicles Available for Your Exact Criteria</h3>
          <p>We could not find an available vehicle matching your journey schedule and party size. This may be due to turnaround cleaning buffers or permanent driver availability.</p>
          <a href="{{ route('booking.ride') }}" class="secondary-button">Adjust Itinerary Criteria</a>
        </div>
      @else
        <div class="vehicles-grid">
          @foreach($suitable as $item)
            @php
              $v = $item->vehicle;
              $est = $item->estimate;
              $media = $v->publicApprovedMedia->first();
            @endphp
            <article class="vehicle-card" aria-labelledby="vehicle-{{ $v->id }}-title">
              <div class="vehicle-card-media">
                @if($media)
                  <img src="{{ Storage::url($media->file_path) }}" alt="{{ $v->name }}" loading="lazy" class="vehicle-img">
                @else
                  <div class="vehicle-img-placeholder" aria-hidden="true">
                    <span>{{ $v->make }} {{ $v->model }}</span>
                  </div>
                @endif
                <span class="category-badge">{{ $v->vehicle_type->getLabel() }}</span>
              </div>

              <div class="vehicle-card-body">
                <h3 id="vehicle-{{ $v->id }}-title" class="vehicle-title">{{ $v->make }} {{ $v->model }} ({{ $v->year }})</h3>
                <p class="vehicle-license-plate">Plate: {{ $v->license_plate }} &bull; Exact Unit</p>

                <ul class="specs-list" aria-label="Vehicle specifications">
                  <li><span aria-hidden="true">👤</span> {{ $v->passenger_capacity }} Passengers max</li>
                  <li><span aria-hidden="true">🧳</span> {{ $v->luggage_capacity }} Large suitcases</li>
                  <li><span aria-hidden="true">❄️</span> {{ $v->has_ac ? 'Dual Air Conditioning' : 'Non-AC' }}</li>
                  <li><span aria-hidden="true">🧑‍✈️</span> Permanent dedicated chauffeur</li>
                </ul>

                <div class="pricing-box">
                  @if($est && $est->isCustomQuote)
                    <div class="custom-quote-badge">
                      <span class="quote-title">Custom Route Quote</span>
                      <small class="quote-sub">Our operations desk will calculate exact pricing upon request submission based on customized stops and route terrain.</small>
                    </div>
                  @elseif($est && $est->grandTotal)
                    <div class="rate-display">
                      <span class="price-amount">{{ $est->grandTotal->currency }} {{ number_format($est->grandTotal->amountMinorUnits / 100, 2) }}</span>
                      @if($est->approximateLkr)
                        <span class="price-approx">(&asymp; LKR {{ number_format($est->approximateLkr->amountMinorUnits / 100, 0) }})</span>
                      @endif
                      <small class="rate-sub">Chauffeured journey &bull; Expressway tolls included</small>
                    </div>
                  @else
                    <div class="custom-quote-badge">
                      <span class="quote-title">Price on Request</span>
                      <small class="quote-sub">Verified upon submission.</small>
                    </div>
                  @endif
                </div>

                <form action="{{ route('booking.ride.hold') }}" method="POST" class="hold-action-form">
                  @csrf
                  <input type="hidden" name="vehicle_id" value="{{ $v->id }}">
                  <button type="submit" class="primary-button select-vehicle-btn">
                    Select &amp; Hold 15 Min &rarr;
                  </button>
                </form>
              </div>
            </article>
          @endforeach
        </div>
      @endif
    </section>

    @if($unsuitable->isNotEmpty())
      <section class="unsuitable-vehicles-section" aria-labelledby="unsuitable-heading" style="margin-top: 3rem;">
        <h2 id="unsuitable-heading" class="section-title text-muted">Other Fleet Vehicles (Currently Unavailable)</h2>
        <p class="section-desc">These vehicles cannot accommodate your journey due to capacity limits or existing schedule commitments.</p>

        <div class="unsuitable-list">
          @foreach($unsuitable as $item)
            @php $v = $item->vehicle; @endphp
            <div class="unsuitable-item">
              <div class="unsuitable-meta">
                <strong>{{ $v->make }} {{ $v->model }} ({{ $v->year }})</strong>
                <span class="plate-tag">{{ $v->license_plate }}</span>
              </div>
              <div class="unsuitable-reasons" style="margin-top: 0.35rem; font-size: 0.85rem; color: var(--color-text-secondary, #64748b);">
                @foreach($item->reasons as $reason)
                  <div class="unsuitable-reason" style="margin-bottom: 0.15rem;">&bull; {{ $reason }}</div>
                @endforeach
              </div>
            </div>
          @endforeach
        </div>
      </section>
    @endif
  </div>
</main>
@endsection
