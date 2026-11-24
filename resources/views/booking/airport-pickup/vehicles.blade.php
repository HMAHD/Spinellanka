@extends('layouts.app')

@section('title', 'Select Vehicle — Airport Pickup — Spinellanka')
@section('meta_description', 'Choose your exact vehicle for airport pickup from Bandaranaike International Airport.')

@section('content')
<main id="main-content" class="booking-page">
  <div class="booking-container">
    <div class="booking-header">
      <nav class="booking-breadcrumbs" aria-label="Breadcrumb">
        <a href="{{ route('home') }}">Home</a> &rsaquo;
        <a href="{{ route('booking.airport-pickup') }}">Flight Details</a> &rsaquo;
        <span aria-current="page">Select Vehicle</span>
      </nav>
      <h1 id="page-title">Select Your Vehicle</h1>
      <p class="booking-subtitle">Choose the exact vehicle for your arrival. When selected, an exclusive 15-minute checkout hold is placed for you.</p>

      <ol class="booking-steps" aria-label="Booking progress">
        <li class="step completed"><span class="step-num">&#10003;</span> <span class="step-title">Flight &amp; Destination</span></li>
        <li class="step active" aria-current="step"><span class="step-num">2</span> <span class="step-title">Select Vehicle</span></li>
        <li class="step"><span class="step-num">3</span> <span class="step-title">Traveler Details</span></li>
        <li class="step"><span class="step-num">4</span> <span class="step-title">Review</span></li>
      </ol>
    </div>

    <!-- Criteria Summary Banner -->
    <section class="criteria-summary-banner" aria-label="Selected flight criteria">
      <div class="criteria-summary-content">
        <div class="summary-pill">
          <span class="pill-label">Flight</span>
          <span class="pill-value">{{ $criteria->flightNumber }} ({{ $criteria->airport }})</span>
        </div>
        <div class="summary-pill">
          <span class="pill-label">Arrival</span>
          <span class="pill-value">{{ $criteria->arrivalTime->format('D, d M Y @ H:i') }} <small>(Asia/Colombo)</small></span>
        </div>
        <div class="summary-pill">
          <span class="pill-label">Destination</span>
          <span class="pill-value">{{ Str::limit($criteria->destinationText, 30) }}</span>
        </div>
        <div class="summary-pill">
          <span class="pill-label">Party</span>
          <span class="pill-value">{{ $criteria->passengerCount }} Pass &bull; {{ $criteria->luggageCount }} Bags</span>
        </div>
      </div>
      <a href="{{ route('booking.airport-pickup') }}" class="edit-criteria-link">Edit flight details</a>
    </section>

    @if(session('error'))
      <div class="alert alert-error" role="alert">
        {{ session('error') }}
      </div>
    @endif

    <section class="vehicles-selection-section" aria-labelledby="suitable-vehicles-heading">
      <h2 id="suitable-vehicles-heading" class="section-title">Available Exact Vehicles ({{ $suitable->count() }})</h2>
      <p class="section-desc">Every vehicle shown is verified for passenger capacity, luggage volume, and has an active driver assigned for your schedule.</p>

      @if($suitable->isEmpty())
        <div class="empty-results-card">
          <h3>No Vehicles Available for Your Exact Criteria</h3>
          <p>We could not find an available vehicle matching your arrival schedule and party size. This may be due to turnaround cleaning buffers or minimum notice requirements.</p>
          <a href="{{ route('booking.airport-pickup') }}" class="secondary-button">Adjust Criteria</a>
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
                  <li><strong>{{ $v->passenger_capacity }}</strong> Passengers</li>
                  <li><strong>{{ $v->luggage_capacity }}</strong> Luggage Bags</li>
                  <li>{{ $v->has_ac ? 'Air Conditioned' : 'Non-AC' }}</li>
                  <li>Chauffeured Driver Included</li>
                </ul>

                <div class="pricing-estimate-box">
                  @if($est && $est->grandTotal)
                    <div class="price-display">
                      <span class="price-currency">USD</span>
                      <span class="price-amount">${{ number_format($est->grandTotal->amountMinorUnits / 100, 2) }}</span>
                    </div>
                    @if($est->approximateLkr)
                      <p class="lkr-approx">Approx. LKR {{ number_format($est->approximateLkr->amountMinorUnits / 100, 0) }}*</p>
                      <small class="rate-basis-note">*LKR approximation for reference only; final settlement basis in USD.</small>
                    @endif
                  @else
                    <div class="price-display custom-quote">
                      <span class="quote-badge">Custom Quote</span>
                    </div>
                    <p class="lkr-approx">Specific route pricing will be confirmed by staff.</p>
                  @endif
                </div>

                <form action="{{ route('booking.airport-pickup.hold') }}" method="POST" class="hold-action-form">
                  @csrf
                  <input type="hidden" name="vehicle_id" value="{{ $v->id }}">
                  <button type="submit" class="select-vehicle-btn">
                    <span>Select &amp; Hold Vehicle</span>
                    <small>15-Min Hold Window</small>
                  </button>
                </form>
              </div>
            </article>
          @endforeach
        </div>
      @endif
    </section>

    @if($unsuitable->isNotEmpty())
      <section class="unsuitable-vehicles-section" aria-labelledby="unsuitable-heading">
        <details class="unsuitable-accordion">
          <summary id="unsuitable-heading" class="unsuitable-summary">
            View {{ $unsuitable->count() }} vehicles unable to service this arrival (Filtered by capacity or driver schedule)
          </summary>
          <div class="unsuitable-list">
            @foreach($unsuitable as $item)
              @php $uv = $item->vehicle; @endphp
              <div class="unsuitable-item">
                <div class="uv-info">
                  <strong>{{ $uv->make }} {{ $uv->model }} ({{ $uv->year }})</strong>
                  <span>Max: {{ $uv->passenger_capacity }} Pass / {{ $uv->luggage_capacity }} Bags</span>
                </div>
                <ul class="uv-reasons">
                  @foreach($item->reasons as $reason)
                    <li>{{ $reason }}</li>
                  @endforeach
                </ul>
              </div>
            @endforeach
          </div>
        </details>
      </section>
    @endif
  </div>
</main>
@endsection
