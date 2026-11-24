@extends('layouts.app')

@section('title', 'Traveler Details — Airport Pickup — Spinellanka')
@section('meta_description', 'Provide traveler contact and flight verification details.')

@section('content')
<main id="main-content" class="booking-page">
  <div class="booking-container">
    <div class="booking-header">
      <nav class="booking-breadcrumbs" aria-label="Breadcrumb">
        <a href="{{ route('home') }}">Home</a> &rsaquo;
        <a href="{{ route('booking.airport-pickup') }}">Flight Details</a> &rsaquo;
        <span aria-current="page">Traveler Details</span>
      </nav>
      <h1 id="page-title">Traveler &amp; Contact Details</h1>
      <p class="booking-subtitle">We coordinate your arrival via WhatsApp. Please provide accurate international contact numbers.</p>

      <ol class="booking-steps" aria-label="Booking progress">
        <li class="step completed"><span class="step-num">&#10003;</span> <span class="step-title">Flight &amp; Destination</span></li>
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
        <ul>
          @foreach($errors->all() as $error)
            <li>{{ $error }}</li>
          @endforeach
        </ul>
      </div>
    @endif

    <div class="booking-content-grid">
      <div class="booking-form-card">
        <form action="{{ route('booking.airport-pickup.submit') }}" method="POST" class="booking-form" novalidate>
          @csrf

          <!-- Booker Contact (Person Paying or Making Arrangement) -->
          <fieldset class="form-section">
            <legend class="section-title">Booker Contact Information</legend>
            <p class="section-desc">The primary contact responsible for this booking arrangement.</p>

            <div class="form-row">
              <div class="form-group">
                <label for="booker_first_name">First Name <span class="required">*</span></label>
                <input type="text" id="booker_first_name" name="booker_first_name" value="{{ old('booker_first_name') }}" required class="form-input @error('booker_first_name') is-invalid @enderror">
                @error('booker_first_name') <span class="form-error">{{ $message }}</span> @enderror
              </div>

              <div class="form-group">
                <label for="booker_last_name">Last Name <span class="required">*</span></label>
                <input type="text" id="booker_last_name" name="booker_last_name" value="{{ old('booker_last_name') }}" required class="form-input @error('booker_last_name') is-invalid @enderror">
                @error('booker_last_name') <span class="form-error">{{ $message }}</span> @enderror
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label for="booker_phone">International Phone / WhatsApp <span class="required">*</span></label>
                <input type="tel" id="booker_phone" name="booker_phone" value="{{ old('booker_phone') }}" placeholder="+44 7700 900123" required class="form-input @error('booker_phone') is-invalid @enderror" aria-describedby="booker-phone-hint">
                <small id="booker-phone-hint" class="form-hint">Include country code (e.g. +44, +1, +49, +61). Primary dispatch channel.</small>
                @error('booker_phone') <span class="form-error">{{ $message }}</span> @enderror
              </div>

              <div class="form-group">
                <label for="booker_email">Email Address (Optional)</label>
                <input type="email" id="booker_email" name="booker_email" value="{{ old('booker_email') }}" placeholder="name@example.com" class="form-input @error('booker_email') is-invalid @enderror">
                <small class="form-hint">For receiving reference link &amp; itinerary summary.</small>
                @error('booker_email') <span class="form-error">{{ $message }}</span> @enderror
              </div>
            </div>

            <input type="hidden" name="booker_is_whatsapp" value="1">
          </fieldset>

          <!-- Lead Traveler Identification -->
          <fieldset class="form-section">
            <legend class="section-title">Lead Traveler at CMB Airport</legend>
            <p class="section-desc">The primary passenger physically arriving on flight {{ $criteria->flightNumber }}.</p>

            <div class="form-checkbox-group">
              <label class="checkbox-label">
                <input type="checkbox" id="lead_traveler_same" name="lead_traveler_same" value="1" {{ old('lead_traveler_same', '1') === '1' ? 'checked' : '' }} onchange="document.getElementById('separate-traveler-fields').style.display = this.checked ? 'none' : 'block'">
                <span>Lead traveler is the same person as the booker above</span>
              </label>
            </div>

            <div id="separate-traveler-fields" style="{{ old('lead_traveler_same', '1') === '1' ? 'display: none;' : 'display: block;' }}">
              <div class="form-row">
                <div class="form-group">
                  <label for="lead_traveler_first_name">Traveler First Name</label>
                  <input type="text" id="lead_traveler_first_name" name="lead_traveler_first_name" value="{{ old('lead_traveler_first_name') }}" class="form-input">
                </div>
                <div class="form-group">
                  <label for="lead_traveler_last_name">Traveler Last Name</label>
                  <input type="text" id="lead_traveler_last_name" name="lead_traveler_last_name" value="{{ old('lead_traveler_last_name') }}" class="form-input">
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label for="lead_traveler_phone">Traveler Mobile / WhatsApp</label>
                  <input type="tel" id="lead_traveler_phone" name="lead_traveler_phone" value="{{ old('lead_traveler_phone') }}" placeholder="+44 ..." class="form-input">
                  <small class="form-hint">Used by our airport greeter to locate traveler upon touchdown.</small>
                </div>
                <div class="form-group">
                  <label for="lead_traveler_email">Traveler Email (Optional)</label>
                  <input type="email" id="lead_traveler_email" name="lead_traveler_email" value="{{ old('lead_traveler_email') }}" class="form-input">
                </div>
              </div>
            </div>
          </fieldset>

          <!-- Policies & Consent -->
          <fieldset class="form-section">
            <legend class="section-title">Policies &amp; Acknowledgements</legend>

            <div class="form-checkbox-group required-consent">
              <label class="checkbox-label @error('terms_accepted') is-invalid @enderror">
                <input type="checkbox" name="terms_accepted" value="1" required {{ old('terms_accepted') ? 'checked' : '' }}>
                <span>I have read and agree to the <a href="{{ route('legal.terms') }}" target="_blank" rel="noopener">Terms of Service</a> and Cancellation Policy. <span class="required">*</span></span>
              </label>
              @error('terms_accepted') <span class="form-error">{{ $message }}</span> @enderror
            </div>

            <div class="form-checkbox-group required-consent">
              <label class="checkbox-label @error('privacy_accepted') is-invalid @enderror">
                <input type="checkbox" name="privacy_accepted" value="1" required {{ old('privacy_accepted') ? 'checked' : '' }}>
                <span>I agree to the processing of contact and flight data in accordance with the <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener">Privacy Policy</a>. <span class="required">*</span></span>
              </label>
              @error('privacy_accepted') <span class="form-error">{{ $message }}</span> @enderror
            </div>

            <div class="form-checkbox-group optional-consent">
              <label class="checkbox-label">
                <input type="checkbox" name="booker_marketing_consent" value="1" {{ old('booker_marketing_consent') ? 'checked' : '' }}>
                <span>Receive occasional Sri Lanka private travel guides and fleet updates (Optional, strictly unbundled).</span>
              </label>
            </div>
          </fieldset>

          <div class="submission-notice-box">
            <h4>No Payment Charged Today</h4>
            <p>Your reservation is submitted as <strong>Pending Under Review</strong>. Our flight dispatch team verifies driver schedule, tracks your incoming flight number, and confirms your booking via WhatsApp.</p>
          </div>

          <div class="form-actions">
            <button type="submit" class="primary-button submit-btn">
              <span>Submit Advance Booking Request &rsaquo;</span>
            </button>
          </div>
        </form>
      </div>

      <aside class="booking-sidebar">
        <div class="summary-card">
          <h3>Reservation Summary</h3>
          <dl class="summary-dl">
            <div>
              <dt>Service</dt>
              <dd>Airport Pickup (CMB)</dd>
            </div>
            <div>
              <dt>Flight</dt>
              <dd>{{ $criteria->flightNumber }}</dd>
            </div>
            <div>
              <dt>Arrival Time</dt>
              <dd>{{ $criteria->arrivalTime->format('d M Y, H:i') }} <small>(Asia/Colombo)</small></dd>
            </div>
            <div>
              <dt>Destination</dt>
              <dd>{{ $criteria->destinationText }}</dd>
            </div>
            <div>
              <dt>Vehicle</dt>
              <dd><strong>{{ $vehicle->make }} {{ $vehicle->model }}</strong></dd>
            </div>
            <div>
              <dt>Party</dt>
              <dd>{{ $criteria->passengerCount }} Passengers &bull; {{ $criteria->luggageCount }} Luggage</dd>
            </div>
          </dl>

          <hr class="summary-divider">

          <div class="pricing-breakdown">
            <h4>Estimate Snapshot</h4>
            @if($estimate && $estimate->items->isNotEmpty())
              <ul class="estimate-items-list">
                @foreach($estimate->items as $item)
                  <li>
                    <span>{{ $item->description }}</span>
                    <span>${{ number_format($item->total_amount_minor_units / 100, 2) }}</span>
                  </li>
                @endforeach
              </ul>
            @endif

            @if($estimate && $estimate->total_amount_minor_units)
              <div class="summary-total-row">
                <span>Total Due on Confirmation:</span>
                <strong>USD ${{ number_format($estimate->total_amount_minor_units / 100, 2) }}</strong>
              </div>
              @if($estimate->approximate_lkr_amount_minor_units)
                <p class="lkr-reference">Approx. LKR {{ number_format($estimate->approximate_lkr_amount_minor_units / 100, 0) }}*</p>
                <small class="rate-basis-note">*Indicative conversion based on Central Bank reference rates.</small>
              @endif
            @else
              <div class="summary-total-row">
                <span>Total:</span>
                <strong>Custom Quote</strong>
              </div>
            @endif
          </div>
        </div>
      </aside>
    </div>
  </div>
</main>
@endsection
