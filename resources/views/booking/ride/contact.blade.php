@extends('layouts.app')

@section('title', 'Traveler Details — Chauffeured Ride — Spinellanka')
@section('meta_description', 'Provide traveler contact and itinerary verification details.')

@section('content')
<main id="main-content" class="booking-page">
  <div class="booking-container">
    <div class="booking-header">
      <nav class="booking-breadcrumbs" aria-label="Breadcrumb">
        <a href="{{ route('home') }}">Home</a> &rsaquo;
        <a href="{{ route('booking.ride') }}">Ride Details</a> &rsaquo;
        <span aria-current="page">Traveler Details</span>
      </nav>
      <h1 id="page-title">Traveler &amp; Contact Details</h1>
      <p class="booking-subtitle">We coordinate your chauffeured journey dispatch via WhatsApp. Please provide accurate international contact numbers.</p>

      <ol class="booking-steps" aria-label="Booking progress">
        <li class="step completed"><span class="step-num">&#10003;</span> <span class="step-title">Route &amp; Timing</span></li>
        <li class="step completed"><span class="step-num">&#10003;</span> <span class="step-title">Select Vehicle</span></li>
        <li class="step active" aria-current="step"><span class="step-num">3</span> <span class="step-title">Traveler Details</span></li>
        <li class="step"><span class="step-num">4</span> <span class="step-title">Review</span></li>
      </ol>
    </div>

    <!-- Active Hold Countdown Banner -->
    <div class="hold-active-banner" role="status" aria-live="polite">
      <div class="hold-indicator-dot" aria-hidden="true"></div>
      <div class="hold-text">
        <strong>Vehicle Hold Active:</strong> We have locked <strong>{{ $vehicle->make }} {{ $vehicle->model }} ({{ $vehicle->license_plate }})</strong> for 15 minutes.
        Complete this form to submit your advance reservation before the hold expires.
      </div>
    </div>

    @if($errors->any())
      <div class="alert alert-error" role="alert">
        <strong>Please correct the following errors:</strong>
        <div class="alert-error-list" style="margin-top: 0.5rem;">
          @foreach($errors->all() as $error)
            <div class="alert-error-item">&bull; {{ $error }}</div>
          @endforeach
        </div>
      </div>
    @endif

    <div class="booking-content-grid">
      <div class="booking-form-card">
        <form action="{{ route('booking.ride.submit') }}" method="POST" class="booking-form" novalidate onsubmit="if (this.dataset.submitting) { return false; } this.dataset.submitting = '1'; const btn = this.querySelector('button[type=submit]'); if (btn) { setTimeout(() => { btn.disabled = true; }, 0); btn.innerText = 'Submitting...'; }">
          @csrf

          <!-- Booker Contact -->
          <fieldset class="form-section">
            <legend class="section-title">Booker Contact Information</legend>
            <p class="section-desc">The primary contact responsible for this booking arrangement.</p>

            <div class="form-row">
              <div class="form-group">
                <label for="booker_first_name">First Name <span class="required">*</span></label>
                <input type="text" id="booker_first_name" name="booker_first_name" value="{{ old('booker_first_name') }}" required autocomplete="given-name" autocapitalize="words" enterkeyhint="next" class="form-input @error('booker_first_name') is-invalid @enderror">
                @error('booker_first_name') <span class="form-error">{{ $message }}</span> @enderror
              </div>

              <div class="form-group">
                <label for="booker_last_name">Last Name <span class="required">*</span></label>
                <input type="text" id="booker_last_name" name="booker_last_name" value="{{ old('booker_last_name') }}" required autocomplete="family-name" autocapitalize="words" enterkeyhint="next" class="form-input @error('booker_last_name') is-invalid @enderror">
                @error('booker_last_name') <span class="form-error">{{ $message }}</span> @enderror
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label for="booker_phone">International Phone / WhatsApp <span class="required">*</span></label>
                <input type="tel" id="booker_phone" name="booker_phone" value="{{ old('booker_phone') }}" placeholder="+44 7700 900123" required autocomplete="tel" inputmode="tel" enterkeyhint="next" class="form-input @error('booker_phone') is-invalid @enderror" aria-describedby="booker-phone-hint">
                <small id="booker-phone-hint" class="form-hint">Include country code (e.g. +44, +1, +49, +61). Primary dispatch channel.</small>
                @error('booker_phone') <span class="form-error">{{ $message }}</span> @enderror
              </div>

              <div class="form-group">
                <label for="booker_email">Email Address (Optional)</label>
                <input type="email" id="booker_email" name="booker_email" value="{{ old('booker_email') }}" placeholder="name@example.com" autocomplete="email" inputmode="email" enterkeyhint="next" class="form-input @error('booker_email') is-invalid @enderror">
                <small class="form-hint">For receiving reference link &amp; itinerary summary.</small>
                @error('booker_email') <span class="form-error">{{ $message }}</span> @enderror
              </div>
            </div>

            <input type="hidden" name="booker_is_whatsapp" value="1">
          </fieldset>

          <!-- Lead Traveler Identification -->
          <fieldset class="form-section">
            <legend class="section-title">Lead Traveler (Passenger)</legend>
            <p class="section-desc">The primary passenger physically taking this chauffeured ride.</p>

            <div class="form-checkbox-group">
              <input type="hidden" name="lead_traveler_same" value="0">
              <label class="checkbox-label">
                <input type="checkbox" id="lead_traveler_same" name="lead_traveler_same" value="1" {{ old('lead_traveler_same', '1') === '1' ? 'checked' : '' }} onchange="document.getElementById('separate-traveler-fields').style.display = this.checked ? 'none' : 'block'">
                <span>Lead traveler is the same person as the booker above</span>
              </label>
            </div>

            <div id="separate-traveler-fields" style="{{ old('lead_traveler_same', '1') === '1' ? 'display: none;' : 'display: block;' }}">
              <div class="form-row">
                <div class="form-group">
                  <label for="lead_traveler_first_name">Passenger First Name <span class="required">*</span></label>
                  <input type="text" id="lead_traveler_first_name" name="lead_traveler_first_name" value="{{ old('lead_traveler_first_name') }}" autocomplete="given-name" autocapitalize="words" enterkeyhint="next" class="form-input @error('lead_traveler_first_name') is-invalid @enderror">
                  @error('lead_traveler_first_name') <span class="form-error">{{ $message }}</span> @enderror
                </div>

                <div class="form-group">
                  <label for="lead_traveler_last_name">Passenger Last Name <span class="required">*</span></label>
                  <input type="text" id="lead_traveler_last_name" name="lead_traveler_last_name" value="{{ old('lead_traveler_last_name') }}" autocomplete="family-name" autocapitalize="words" enterkeyhint="next" class="form-input @error('lead_traveler_last_name') is-invalid @enderror">
                  @error('lead_traveler_last_name') <span class="form-error">{{ $message }}</span> @enderror
                </div>
              </div>

              <div class="form-row">
                <div class="form-group">
                  <label for="lead_traveler_phone">Passenger WhatsApp / Mobile <span class="required">*</span></label>
                  <input type="tel" id="lead_traveler_phone" name="lead_traveler_phone" value="{{ old('lead_traveler_phone') }}" placeholder="+44 7700 900123" autocomplete="tel" inputmode="tel" enterkeyhint="next" class="form-input @error('lead_traveler_phone') is-invalid @enderror">
                  <small class="form-hint">Direct number for chauffeur to locate passenger at pickup.</small>
                  @error('lead_traveler_phone') <span class="form-error">{{ $message }}</span> @enderror
                </div>

                <div class="form-group">
                  <label for="lead_traveler_email">Passenger Email (Optional)</label>
                  <input type="email" id="lead_traveler_email" name="lead_traveler_email" value="{{ old('lead_traveler_email') }}" placeholder="passenger@example.com" autocomplete="email" inputmode="email" enterkeyhint="next" class="form-input @error('lead_traveler_email') is-invalid @enderror">
                  @error('lead_traveler_email') <span class="form-error">{{ $message }}</span> @enderror
                </div>
              </div>

              <input type="hidden" name="lead_traveler_is_whatsapp" value="1">
            </div>
          </fieldset>

          <!-- Policy & Consent -->
          <fieldset class="form-section">
            <legend class="section-title">Required Agreements &amp; Consent</legend>

            <div class="consent-group">
              <label class="checkbox-label required-consent">
                <input type="checkbox" name="terms_accepted" value="1" required {{ old('terms_accepted') ? 'checked' : '' }} class="@error('terms_accepted') is-invalid @enderror">
                <span>
                  I accept the <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener">Terms &amp; Conditions</a>, including the 45-minute courtesy waiting policy and route itinerary terms. <span class="required">*</span>
                </span>
              </label>
              @error('terms_accepted') <span class="form-error">{{ $message }}</span> @enderror

              <label class="checkbox-label required-consent">
                <input type="checkbox" name="privacy_accepted" value="1" required {{ old('privacy_accepted') ? 'checked' : '' }} class="@error('privacy_accepted') is-invalid @enderror">
                <span>
                  I consent to the processing of contact details for journey dispatch in accordance with the <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener">Privacy Policy</a>. <span class="required">*</span>
                </span>
              </label>
              @error('privacy_accepted') <span class="form-error">{{ $message }}</span> @enderror

              <label class="checkbox-label optional-consent">
                <input type="checkbox" name="booker_marketing_consent" value="1" {{ old('booker_marketing_consent') ? 'checked' : '' }}>
                <span>Keep me informed with seasonal travel guides and curated private tours in Sri Lanka (Optional).</span>
              </label>
            </div>
          </fieldset>

          <div class="form-actions" style="margin-top: 1.5rem;">
            <button type="submit" class="primary-button submit-btn">
              Submit Advance Ride Request &rarr;
            </button>
          </div>
        </form>
      </div>

      <!-- Sidebar Summary Card -->
      <aside class="booking-sidebar">
        <div class="sidebar-card">
          <h2 class="sidebar-title">Journey Summary</h2>

          <div class="summary-itinerary-box">
            <div class="itinerary-row">
              <span class="itinerary-label">Origin:</span>
              <span class="itinerary-val">{{ $criteria->originText }}</span>
            </div>
            @if(!empty($criteria->stops))
              <div class="itinerary-row">
                <span class="itinerary-label">En-Route Stops:</span>
                <span class="itinerary-val">{{ count($criteria->stops) }} stops ({{ $criteria->totalWaitingMinutes() }} min total wait)</span>
              </div>
            @endif
            <div class="itinerary-row">
              <span class="itinerary-label">Destination:</span>
              <span class="itinerary-val">{{ $criteria->destinationText }}</span>
            </div>
            <div class="itinerary-row">
              <span class="itinerary-label">Pickup Time:</span>
              <span class="itinerary-val">{{ $criteria->pickupTime->format('D, d M Y @ H:i') }}</span>
            </div>
            @if($criteria->hasReturn)
              <div class="itinerary-row">
                <span class="itinerary-label">Return Leg:</span>
                <span class="itinerary-val">{{ $criteria->returnPickupTime?->format('D, d M Y @ H:i') }}</span>
              </div>
            @endif
          </div>

          <hr class="summary-divider">

          <div class="summary-vehicle-box">
            <span class="itinerary-label">Reserved Exact Unit:</span>
            <strong>{{ $vehicle->make }} {{ $vehicle->model }} ({{ $vehicle->year }})</strong>
            <p class="plate-text">License Plate: {{ $vehicle->license_plate }} &bull; Dedicated Chauffeur</p>
          </div>

          <hr class="summary-divider">

          <div class="summary-pricing-box">
            <div class="sidebar-price-row total-row">
              <span>Total Estimate</span>
              @if($estimate->is_custom_quote)
                <span class="price-val">Custom Quote</span>
              @elseif($estimate->total_amount_minor_units !== null)
                <span class="price-val">{{ $estimate->currency }} {{ number_format($estimate->total_amount_minor_units / 100, 2) }}</span>
              @else
                <span class="price-val">Quote on Request</span>
              @endif
            </div>

            <p class="sidebar-fee-note">
              &bull; Southern/Central expressway tolls included<br>
              &bull; 45-min courtesy waiting at origin included<br>
              &bull; Unplanned route adjustments settled with operations desk<br>
              &bull; Site admission tickets &amp; parking fees excluded
            </p>
          </div>
        </div>
      </aside>
    </div>
  </div>
</main>
@endsection
