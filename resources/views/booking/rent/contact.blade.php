@extends('layouts.app')

@section('title', 'Driver & Contact Details — Rent — Spinellanka')
@section('meta_description', 'Provide traveler contact and lead driver verification details for self-drive vehicle hire.')

@section('content')
<main id="main-content" class="booking-page">
  <div class="booking-container">
    <div class="booking-header">
      <nav class="booking-breadcrumbs" aria-label="Breadcrumb">
        <a href="{{ route('home') }}">Home</a> &rsaquo;
        <a href="{{ route('booking.rent') }}">Rental Dates</a> &rsaquo;
        <a href="{{ route('booking.rent.vehicles') }}">Select Vehicle</a> &rsaquo;
        <span aria-current="page">Review &amp; Contact</span>
      </nav>
      <h1 id="page-title">Driver &amp; Contact Information</h1>
      <p class="booking-subtitle">We coordinate your handover via WhatsApp. Please verify all contact details and document requirements.</p>

      <ol class="booking-steps" aria-label="Booking progress">
        <li class="step completed"><span class="step-num">&#10003;</span> <span class="step-title">Dates &amp; Eligibility</span></li>
        <li class="step completed"><span class="step-num">&#10003;</span> <span class="step-title">Select Vehicle</span></li>
        <li class="step active" aria-current="step"><span class="step-num">3</span> <span class="step-title">Driver &amp; Review</span></li>
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
        <form action="{{ route('booking.rent.submit') }}" method="POST" class="booking-form" novalidate onsubmit="if (this.dataset.submitting) { return false; } this.dataset.submitting = '1'; const btn = this.querySelector('button[type=submit]'); if (btn) { setTimeout(() => { btn.disabled = true; }, 0); btn.innerText = 'Submitting...'; }">
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
                <label for="booker_phone">Phone / WhatsApp (with international country code) <span class="required">*</span></label>
                <input type="tel" id="booker_phone" name="booker_phone" value="{{ old('booker_phone') }}" placeholder="+44 7700 900077" required autocomplete="tel" enterkeyhint="next" class="form-input @error('booker_phone') is-invalid @enderror" aria-describedby="phone-hint">
                <small id="phone-hint" class="form-hint">Include full international prefix. Our operations team coordinates vehicle handover via WhatsApp.</small>
                @error('booker_phone') <span class="form-error">{{ $message }}</span> @enderror
              </div>

              <div class="form-group">
                <label for="booker_email">Email Address (Optional)</label>
                <input type="email" id="booker_email" name="booker_email" value="{{ old('booker_email') }}" placeholder="traveler@example.com" autocomplete="email" enterkeyhint="next" class="form-input @error('booker_email') is-invalid @enderror">
                @error('booker_email') <span class="form-error">{{ $message }}</span> @enderror
              </div>
            </div>

            <div class="form-checkbox-row">
              <label class="checkbox-label">
                <input type="checkbox" name="booker_is_whatsapp" value="1" {{ old('booker_is_whatsapp', '1') === '1' ? 'checked' : '' }}>
                <span>This mobile phone number is active on WhatsApp (recommended for handover coordination)</span>
              </label>
            </div>
          </fieldset>

          <!-- Lead Driver Relationship -->
          <fieldset class="form-section">
            <legend class="section-title">Lead Driver Details</legend>
            <p class="section-desc">The primary driver who will take delivery of the vehicle and sign the physical rental agreement.</p>

            <div class="form-checkbox-row">
              <label class="checkbox-label">
                <input type="checkbox" id="lead_driver_same_as_booker" name="lead_driver_same_as_booker" value="1" {{ old('lead_driver_same_as_booker', '1') === '1' ? 'checked' : '' }} onchange="document.getElementById('lead-driver-separate-box').style.display = this.checked ? 'none' : 'block';">
                <span>The Booker is the Lead Driver</span>
              </label>
            </div>

            <div id="lead-driver-separate-box" style="display: {{ old('lead_driver_same_as_booker', '1') === '1' ? 'none' : 'block' }}; margin-top: 1rem; padding: 1rem; background: var(--color-surface, #f8fafc); border-radius: 6px;">
              <div class="form-row">
                <div class="form-group">
                  <label for="lead_driver_first_name">Driver First Name</label>
                  <input type="text" id="lead_driver_first_name" name="lead_driver_first_name" value="{{ old('lead_driver_first_name') }}" class="form-input">
                </div>
                <div class="form-group">
                  <label for="lead_driver_last_name">Driver Last Name</label>
                  <input type="text" id="lead_driver_last_name" name="lead_driver_last_name" value="{{ old('lead_driver_last_name') }}" class="form-input">
                </div>
              </div>

              <div class="form-group">
                <label for="lead_driver_phone">Driver Mobile / WhatsApp</label>
                <input type="tel" id="lead_driver_phone" name="lead_driver_phone" value="{{ old('lead_driver_phone') }}" class="form-input">
              </div>
            </div>
          </fieldset>

          <!-- Mandatory Acknowledgement & Policies -->
          <fieldset class="form-section">
            <legend class="section-title">Legal Terms &amp; Document Acknowledgement</legend>

            <!-- Document Inspection Acknowledgement -->
            <div class="alert alert-warning" style="margin-bottom: 1.25rem; background: #fffbeb; border: 1px solid #fef3c7; border-left: 4px solid #f59e0b; padding: 1rem; border-radius: 4px;">
              <label class="checkbox-label" style="display: flex; gap: 0.75rem; cursor: pointer; align-items: flex-start;">
                <input type="checkbox" id="original_documents_acknowledged" name="original_documents_acknowledged" value="1" {{ old('original_documents_acknowledged') ? 'checked' : '' }} required style="margin-top: 0.25rem;">
                <span style="font-size: 0.875rem;">
                  <strong>Physical Original Documents Requirement <span class="required">*</span></strong><br>
                  I acknowledge and understand that the lead driver must present <strong>original physical documents</strong> (Passport with valid entry visa, Home Country Driving Licence, and International Driving Permit) in person at vehicle handover. Vehicle release is strictly prohibited without physical originals; photocopies or digital photos on phone cannot be accepted.
                </span>
              </label>
              @error('original_documents_acknowledged') <span class="form-error" style="display:block; margin-top: 0.5rem;">{{ $message }}</span> @enderror
            </div>

            <!-- Terms Acceptance -->
            <div class="form-checkbox-row" style="margin-bottom: 0.75rem;">
              <label class="checkbox-label" style="display: flex; gap: 0.5rem; cursor: pointer;">
                <input type="checkbox" id="terms_accepted" name="terms_accepted" value="1" {{ old('terms_accepted') ? 'checked' : '' }} required>
                <span>I accept the <a href="{{ route('legal.terms') }}" target="_blank" style="text-decoration: underline;">Spinellanka Terms of Service</a> and Self-Drive Rental Policy <span class="required">*</span></span>
              </label>
              @error('terms_accepted') <span class="form-error">{{ $message }}</span> @enderror
            </div>

            <!-- Privacy Acceptance -->
            <div class="form-checkbox-row">
              <label class="checkbox-label" style="display: flex; gap: 0.5rem; cursor: pointer;">
                <input type="checkbox" id="privacy_accepted" name="privacy_accepted" value="1" {{ old('privacy_accepted') ? 'checked' : '' }} required>
                <span>I accept the <a href="{{ route('legal.privacy') }}" target="_blank" style="text-decoration: underline;">Privacy Policy</a> for reservation processing <span class="required">*</span></span>
              </label>
              @error('privacy_accepted') <span class="form-error">{{ $message }}</span> @enderror
            </div>
          </fieldset>

          <div class="form-actions" style="margin-top: 2rem;">
            <button type="submit" class="submit-button" style="width: 100%; min-height: 48px; font-weight: 600;">
              Submit Advance Rental Request &rsaquo;
            </button>
          </div>
        </form>
      </div>

      <!-- Right Column: Summary Card -->
      <aside class="booking-side-col">
        <div class="booking-card pricing-card">
          <h2 class="card-heading">Rental Summary</h2>

          <div style="margin-bottom: 1rem; padding-bottom: 1rem; border-bottom: 1px solid var(--color-border, #e2e8f0);">
            <h3 style="font-size: 1.125rem; font-weight: 700; margin: 0;">{{ $vehicle->make }} {{ $vehicle->model }} ({{ $vehicle->year }})</h3>
            <p style="margin: 0.25rem 0 0; font-size: 0.8125rem; color: var(--color-text-muted, #64748b);">Exact Vehicle Plate: <strong>{{ $vehicle->license_plate }}</strong></p>
          </div>

          <dl class="details-grid" style="font-size: 0.875rem; margin-bottom: 1rem;">
            <div>
              <dt>Period</dt>
              <dd><strong>{{ $criteria->durationDays }} Days{{ $criteria->extraHours > 0 ? ' + '.$criteria->extraHours.'h' : '' }}</strong></dd>
            </div>
            <div>
              <dt>Pickup</dt>
              <dd>{{ $criteria->pickupDateTime->format('D, d M @ H:i') }} ({{ ucfirst($criteria->pickupLocationType) }})</dd>
            </div>
            <div>
              <dt>Return</dt>
              <dd>{{ $criteria->returnDateTime->format('D, d M @ H:i') }} ({{ ucfirst($criteria->returnLocationType) }})</dd>
            </div>
            <div>
              <dt>Included Distance</dt>
              <dd>{{ $criteria->durationDays * $policy->daily_mileage_km }} km allowance</dd>
            </div>
          </dl>

          @if($estimate && $estimate->items->isNotEmpty())
            <div class="breakdown-list" style="margin-top: 1rem; border-top: 1px solid var(--color-border, #e2e8f0); padding-top: 1rem;">
              @foreach($estimate->items as $item)
                <div class="sidebar-price-row" style="display: flex; justify-content: space-between; margin-bottom: 0.35rem; font-size: 0.875rem;">
                  <span>{{ $item->description }}</span>
                  <span>${{ number_format($item->total_amount_minor_units / 100, 2) }}</span>
                </div>
              @endforeach
            </div>
          @endif

          <div class="total-row" style="margin-top: 1rem; padding-top: 1rem; border-top: 2px solid var(--color-border, #e2e8f0);">
            <span>Total Estimated Fee:</span>
            @if($estimate)
              <strong>USD ${{ number_format($estimate->total_amount_minor_units / 100, 2) }}</strong>
            @else
              <strong>Custom Quote</strong>
            @endif
          </div>

          @if($estimate && $estimate->approximate_lkr_amount_minor_units)
            <div class="lkr-basis-box" style="margin-top: 0.75rem;">
              <span class="lkr-label">Approximate LKR Basis:</span>
              <span class="lkr-val">LKR {{ number_format($estimate->approximate_lkr_amount_minor_units / 100, 0) }}*</span>
              <small class="lkr-disclaimer">*Indicative rate for local reference. Final settlement amount is in USD.</small>
            </div>
          @endif

          <div class="payment-policy-note" style="margin-top: 1rem;">
            <small><strong>No payment today:</strong> Advance request only. Payment and deposit pre-authorization are processed upon vehicle handover.</small>
          </div>
        </div>
      </aside>
    </div>
  </div>
</main>
@endsection

