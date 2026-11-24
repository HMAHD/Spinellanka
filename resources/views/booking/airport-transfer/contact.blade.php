@extends('layouts.app')

@section('title', 'Traveler Details — Airport Transfer — Spinellanka')
@section('meta_description', 'Provide traveler contact and flight departure verification details.')

@section('content')
<main id="main-content" class="booking-page">
  <div class="booking-container">
    <div class="booking-header">
      <nav class="booking-breadcrumbs" aria-label="Breadcrumb">
        <a href="{{ route('home') }}">Home</a> &rsaquo;
        <a href="{{ route('booking.airport-transfer') }}">Departure Details</a> &rsaquo;
        <span aria-current="page">Traveler Details</span>
      </nav>
      <h1 id="page-title">Traveler &amp; Contact Details</h1>
      <p class="booking-subtitle">We coordinate your departure transfer via WhatsApp. Please provide accurate international contact numbers.</p>

      <ol class="booking-steps" aria-label="Booking progress">
        <li class="step completed"><span class="step-num">&#10003;</span> <span class="step-title">Flight &amp; Origin</span></li>
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
        <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
      </div>
    @endif

    <div class="booking-content-grid">
      <div class="booking-form-card">
        <form action="{{ route('booking.airport-transfer.submit') }}" method="POST" class="booking-form" novalidate onsubmit="if (this.dataset.submitting) { return false; } this.dataset.submitting = '1'; const btn = this.querySelector('button[type=submit]'); if (btn) { setTimeout(() => { btn.disabled = true; }, 0); btn.innerText = 'Submitting...'; }">
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
            <p class="section-desc">The primary passenger physically taking this transfer to flight {{ $criteria->flightNumber }}.</p>

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
                  <label for="lead_traveler_first_name">Traveler First Name</label>
                  <input type="text" id="lead_traveler_first_name" name="lead_traveler_first_name" value="{{ old('lead_traveler_first_name') }}" autocomplete="given-name" autocapitalize="words" enterkeyhint="next" class="form-input">
                </div>
                <div class="form-group">
                  <label for="lead_traveler_last_name">Traveler Last Name</label>
                  <input type="text" id="lead_traveler_last_name" name="lead_traveler_last_name" value="{{ old('lead_traveler_last_name') }}" autocomplete="family-name" autocapitalize="words" enterkeyhint="next" class="form-input">
                </div>
              </div>
              <div class="form-row">
                <div class="form-group">
                  <label for="lead_traveler_phone">Traveler Mobile / WhatsApp</label>
                  <input type="tel" id="lead_traveler_phone" name="lead_traveler_phone" value="{{ old('lead_traveler_phone') }}" placeholder="+44 ..." autocomplete="tel" inputmode="tel" enterkeyhint="next" class="form-input">
                  <small class="form-hint">Used by driver to coordinate hotel pickup.</small>
                </div>
                <div class="form-group">
                  <label for="lead_traveler_email">Traveler Email (Optional)</label>
                  <input type="email" id="lead_traveler_email" name="lead_traveler_email" value="{{ old('lead_traveler_email') }}" autocomplete="email" inputmode="email" enterkeyhint="next" class="form-input">
                </div>
              </div>
            </div>
          </fieldset>

          <!-- Policies & Consent -->
          <fieldset class="form-section">
            <legend class="section-title">Policies &amp; Acknowledgements</legend>

            @if($criteria->isUnsafeTime)
              <div class="warning-banner" role="alert" style="margin-bottom: 1rem;">
                <div class="warning-title">⚠️ Missed-Flight Risk Warning</div>
                <p class="warning-text">Your chosen pickup time (<strong>{{ $criteria->chosenPickupTime->format('H:i') }}</strong>) is later than our recommended time (<strong>{{ $criteria->recommendedPickupTime->format('H:i') }}</strong>). Arriving less than 3 hours before departure increases the risk of missing your flight due to traffic or airport queues.</p>
              </div>
            @endif

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
                <span>I agree to the processing of contact and journey data in accordance with the <a href="{{ route('legal.privacy') }}" target="_blank" rel="noopener">Privacy Policy</a>. <span class="required">*</span></span>
              </label>
              @error('privacy_accepted') <span class="form-error">{{ $message }}</span> @enderror
            </div>

            <div class="form-checkbox-group optional-consent">
              <label class="checkbox-label">
                <input type="checkbox" name="booker_marketing_consent" value="1" {{ old('booker_marketing_consent') ? 'checked' : '' }}>
                <span>Receive occasional Sri Lanka private travel guides and fleet updates (Optional, strictly unbundled).</span>
              </label>
            </div>

            <!-- Approved Liability Disclaimer -->
            <div class="legal-notice-box" role="note">
              <strong>Airport Departure Liability Notice:</strong>
              <p>Spinellanka provides chauffeured airport transfers based on estimated travel times and client-selected pickup times. Spinellanka does not guarantee airline check-in or flight departure and accepts no financial or legal liability for missed flights resulting from customer-chosen departure times, airline schedule changes, airport security/immigration queues, or unforeseen road conditions.</p>
            </div>
          </fieldset>

          <div class="form-actions">
            <button type="submit" class="primary-button">Submit Advance Transfer Request &rsaquo;</button>
          </div>
        </form>
      </div>

      <!-- Sidebar Order Summary -->
      <aside class="booking-sidebar">
        <div class="order-summary-card">
          <h2 class="summary-heading">Transfer Summary</h2>

          <div class="summary-vehicle-preview">
            @php $media = $vehicle->publicApprovedMedia->first(); @endphp
            @if($media)
              <img src="{{ Storage::url($media->file_path) }}" alt="{{ $vehicle->name }}" class="preview-img">
            @endif
            <div class="preview-info">
              <strong>{{ $vehicle->make }} {{ $vehicle->model }}</strong>
              <span class="preview-plate">Plate: {{ $vehicle->license_plate }}</span>
            </div>
          </div>

          <dl class="summary-dl">
            <div class="dl-row">
              <dt>Service</dt>
              <dd>Airport Transfer (Departure)</dd>
            </div>
            <div class="dl-row">
              <dt>Airport</dt>
              <dd>CMB (Katunayake)</dd>
            </div>
            <div class="dl-row">
              <dt>Flight Number</dt>
              <dd><strong>{{ $criteria->flightNumber }}</strong></dd>
            </div>
            <div class="dl-row">
              <dt>Departure Takeoff</dt>
              <dd>{{ $criteria->departureTime->format('D, d M @ H:i') }}</dd>
            </div>
            <div class="dl-row">
              <dt>Origin Pickup</dt>
              <dd>{{ Str::limit($criteria->originText, 30) }}</dd>
            </div>
            <div class="dl-row highlight-row">
              <dt>Scheduled Pickup</dt>
              <dd>
                <strong>{{ $criteria->chosenPickupTime->format('D, d M @ H:i') }}</strong>
                @if($criteria->isUnsafeTime)
                  <span class="badge badge-warning">Later than Rec.</span>
                @else
                  <span class="badge badge-success">Safe Rec.</span>
                @endif
              </dd>
            </div>
            <div class="dl-row">
              <dt>Tolls &amp; Fees</dt>
              <dd>Expressway &amp; Drop-off Included</dd>
            </div>
            <div class="dl-row">
              <dt>Party</dt>
              <dd>{{ $criteria->passengerCount }} Pass / {{ $criteria->luggageCount }} Bags</dd>
            </div>
          </dl>

          <div class="summary-total-box">
            @if($estimate && $estimate->total_amount_minor_units !== null)
              <div class="total-row">
                <span>Estimated Total:</span>
                <strong class="total-amount">${{ number_format($estimate->total_amount_minor_units / 100, 2) }} {{ $estimate->currency }}</strong>
              </div>
              @if($estimate->approximate_lkr_amount_minor_units)
                <p class="lkr-hint">Approx. LKR {{ number_format($estimate->approximate_lkr_amount_minor_units / 100, 0) }}*</p>
              @endif
            @else
              <div class="total-row">
                <span>Estimated Total:</span>
                <strong class="total-amount">Custom Quote</strong>
              </div>
              <p class="lkr-hint">Specific route pricing will be confirmed by staff.</p>
            @endif
          </div>
        </div>
      </aside>
    </div>
  </div>
</main>
@endsection

