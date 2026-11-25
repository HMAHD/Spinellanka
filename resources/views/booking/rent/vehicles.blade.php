@extends('layouts.app')

@section('title', 'Select Vehicle — Rent — Spinellanka')
@section('meta_description', 'Choose your exact self-drive vehicle. An exclusive 15-minute checkout hold is established upon selection.')

@section('content')
<main id="main-content" class="booking-page">
  <div class="booking-container">
    <div class="booking-header">
      <nav class="booking-breadcrumbs" aria-label="Breadcrumb">
        <a href="{{ route('home') }}">Home</a> &rsaquo;
        <a href="{{ route('booking.rent') }}">Rental Dates</a> &rsaquo;
        <span aria-current="page">Select Vehicle</span>
      </nav>
      <h1 id="page-title">Select Your Self-Drive Vehicle</h1>
      <p class="booking-subtitle">Choose the exact vehicle for your journey. An exclusive 15-minute checkout hold is established upon selection.</p>

      <ol class="booking-steps" aria-label="Booking progress">
        <li class="step completed"><span class="step-num">&#10003;</span> <span class="step-title">Dates &amp; Eligibility</span></li>
        <li class="step active" aria-current="step"><span class="step-num">2</span> <span class="step-title">Select Vehicle</span></li>
        <li class="step"><span class="step-num">3</span> <span class="step-title">Driver &amp; Review</span></li>
      </ol>
    </div>

    <!-- Criteria Summary Banner -->
    <section class="criteria-summary-banner" aria-label="Selected rental criteria">
      <div class="criteria-summary-content">
        <div class="summary-pill">
          <span class="pill-label">Pickup</span>
          <span class="pill-value">{{ $criteria->pickupDateTime->format('D, d M @ H:i') }}</span>
        </div>
        <div class="summary-pill">
          <span class="pill-label">Return</span>
          <span class="pill-value">{{ $criteria->returnDateTime->format('D, d M @ H:i') }}</span>
        </div>
        <div class="summary-pill">
          <span class="pill-label">Duration</span>
          <span class="pill-value"><strong>{{ $criteria->durationDays }} Days{{ $criteria->extraHours > 0 ? ' + '.$criteria->extraHours.'h' : '' }}</strong></span>
        </div>
        <div class="summary-pill">
          <span class="pill-label">Handover</span>
          <span class="pill-value">{{ ucfirst($criteria->pickupLocationType) }} &rarr; {{ ucfirst($criteria->returnLocationType) }}</span>
        </div>
        <div class="summary-pill">
          <span class="pill-label">Driver</span>
          <span class="pill-value">Age {{ $criteria->driverAge }} ({{ $criteria->driverLicenceCountry }})</span>
        </div>
      </div>
      <a href="{{ route('booking.rent') }}" class="edit-criteria-link">Edit criteria</a>
    </section>

    @if(session('error'))
      <div class="alert alert-error" role="alert">
        {{ session('error') }}
      </div>
    @endif

    <section class="vehicles-selection-section" aria-labelledby="suitable-vehicles-heading">
      <h2 id="suitable-vehicles-heading" class="section-title">Available Exact Vehicles ({{ $suitability->suitable->count() }})</h2>
      <p class="section-desc">Every vehicle shown is verified eligible for self-drive hire, fully prepared, and available without schedule conflicts.</p>

      @if($suitability->suitable->isEmpty())
        <div class="empty-results-card">
          <h3>No Vehicles Available for Your Exact Criteria</h3>
          <p>We could not find an available self-drive vehicle matching your dates and eligibility. This may be due to turnaround cleaning buffers or age/licence criteria.</p>
          <a href="{{ route('booking.rent') }}" class="secondary-button">Adjust Rental Criteria</a>
        </div>
      @else
        <div class="vehicles-grid">
          @foreach($suitability->suitable as $item)
            @php
              $v = $item->vehicle;
              $est = $item->estimate;
            @endphp
            <div class="vehicle-card">
              <div class="vehicle-card-media">
                @if($v->publicApprovedMedia->isNotEmpty())
                  <img src="{{ Storage::url($v->publicApprovedMedia->first()->file_path) }}" alt="{{ $v->publicApprovedMedia->first()->alt_text ?? $v->public_name }}" loading="lazy" class="vehicle-card-img">
                @else
                  <div class="vehicle-card-placeholder" aria-label="Photo pending verification">
                    <span class="placeholder-icon">📷</span>
                    <span class="placeholder-text">Photo Pending Verification</span>
                  </div>
                @endif
              </div>

              <div class="vehicle-card-content">
                <div class="vehicle-card-header">
                  <h3 class="vehicle-name">{{ $v->make }} {{ $v->model }} ({{ $v->year }})</h3>
                  <span class="vehicle-type-pill">{{ $v->vehicle_type->getLabel() }}</span>
                </div>

                <div class="exact-vehicle-badge">
                  <span>Exact Vehicle License Plate: <strong>{{ $v->license_plate }}</strong></span>
                </div>

                <div class="vehicle-features-grid">
                  <div class="feature-item">
                    <span class="feature-icon">👤</span>
                    <span class="feature-val">{{ $v->passenger_capacity }} Seats</span>
                  </div>
                  <div class="feature-item">
                    <span class="feature-icon">🧳</span>
                    <span class="feature-val">{{ $v->luggage_capacity }} Bags</span>
                  </div>
                  <div class="feature-item">
                    <span class="feature-icon">⚙️</span>
                    <span class="feature-val">{{ ucfirst($v->transmission->value) }}</span>
                  </div>
                  <div class="feature-item">
                    <span class="feature-icon">❄️</span>
                    <span class="feature-val">{{ $v->has_ac ? 'A/C' : 'Non-A/C' }}</span>
                  </div>
                </div>

                <div class="self-drive-badge" style="margin: 0.75rem 0; padding: 0.5rem 0.75rem; background: rgba(15, 23, 42, 0.04); border-radius: 4px; font-size: 0.8125rem;">
                  <strong>Self-Drive Hire:</strong> No driver provided &bull; {{ $criteria->durationDays * $policy->daily_mileage_km }} km allowance included
                </div>

                <div class="vehicle-pricing-section">
                  <div class="price-block">
                    @if($est && $est->grandTotal)
                      <span class="price-amount">${{ number_format($est->grandTotal->amountMinorUnits / 100, 2) }} USD</span>
                      <span class="price-subtext">Total for {{ $criteria->durationDays }} days (includes ${{ number_format($policy->security_deposit_minor_units / 100, 0) }} deposit)</span>
                    @else
                      <span class="price-custom-quote">Custom Quote</span>
                      <span class="price-subtext">Pricing requires staff verification</span>
                    @endif
                  </div>

                  <form action="{{ route('booking.rent.hold') }}" method="POST" class="hold-action-form">
                    @csrf
                    <input type="hidden" name="vehicle_id" value="{{ $v->id }}">
                    <button type="submit" class="primary-button select-vehicle-btn">
                      Select Vehicle (15m Hold) &rsaquo;
                    </button>
                  </form>
                </div>
              </div>
            </div>
          @endforeach
        </div>
      @endif
    </section>

    @if($suitability->unsuitable->isNotEmpty())
      <section class="unsuitable-vehicles-section" style="margin-top: 3rem; border-top: 1px solid var(--color-border, #e2e8f0); padding-top: 2rem;">
        <h3 style="font-size: 1.125rem; font-weight: 600; color: var(--color-text-muted, #64748b);">Vehicles Unavailable for this Rental ({{ $suitability->unsuitable->count() }})</h3>
        <p style="font-size: 0.875rem; color: var(--color-text-muted, #64748b);">The following vehicles do not meet your requested dates or eligibility requirements:</p>
        <div style="display: flex; flex-direction: column; gap: 0.75rem; margin-top: 1rem;">
          @foreach($suitability->unsuitable as $item)
            <div style="padding: 0.75rem 1rem; background: var(--color-surface, #f8fafc); border-radius: 6px; border: 1px solid var(--color-border, #e2e8f0); display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.5rem;">
              <div>
                <strong>{{ $item->vehicle->make }} {{ $item->vehicle->model }} ({{ $item->vehicle->year }})</strong>
                <span style="font-size: 0.8125rem; color: var(--color-text-muted, #64748b); margin-left: 0.5rem;">({{ $item->vehicle->license_plate }})</span>
              </div>
              <ul style="margin: 0; padding-left: 1.25rem; font-size: 0.8125rem; color: #dc2626;">
                @foreach($item->reasons as $reason)
                  <li>{{ $reason }}</li>
                @endforeach
              </ul>
            </div>
          @endforeach
        </div>
      </section>
    @endif
  </div>
</main>
@endsection
