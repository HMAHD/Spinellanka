@extends('layouts.app')

@section('title', 'Self-Drive Vehicle Hire — Rent — Spinellanka')
@section('meta_description', 'Reserve an exact self-drive vehicle in Sri Lanka for your chosen dates. Transparent 24-hour cycles, no driver, in-person document check at handover.')

@section('content')
<main id="main-content" class="booking-page">
  <div class="booking-container">
    <div class="booking-header">
      <nav class="booking-breadcrumbs" aria-label="Breadcrumb">
        <a href="{{ route('home') }}">Home</a> &rsaquo;
        <a href="{{ route('services.show', ['service' => 'rent']) }}">Rent</a> &rsaquo;
        <span aria-current="page">Book</span>
      </nav>
      <h1 id="page-title">Self-Drive Vehicle Hire</h1>
      <p class="booking-subtitle">Reserve an exact vehicle from our verified fleet for independent travel in Sri Lanka. 24-hour hire cycles apply. Strictly self-drive with no driver provided.</p>

      <ol class="booking-steps" aria-label="Booking progress">
        <li class="step active" aria-current="step"><span class="step-num">1</span> <span class="step-title">Dates &amp; Eligibility</span></li>
        <li class="step"><span class="step-num">2</span> <span class="step-title">Select Vehicle</span></li>
        <li class="step"><span class="step-num">3</span> <span class="step-title">Driver &amp; Review</span></li>
      </ol>
    </div>

    @if(session('error'))
      <div class="alert alert-error" role="alert">
        {{ session('error') }}
      </div>
    @endif

    @if($errors->any())
      <div class="alert alert-error" role="alert">
        <strong>Please check the following details:</strong>
        <div class="alert-error-list" style="margin-top: 0.5rem;">
          @foreach($errors->all() as $error)
            <div class="alert-error-item">&bull; {{ $error }}</div>
          @endforeach
        </div>
      </div>
    @endif

    <div class="booking-content-grid">
      <div class="booking-form-card">
        <form action="{{ route('booking.rent.search') }}" method="POST" class="booking-form" novalidate>
          @csrf

          <!-- Service Model Notice -->
          <div class="alert alert-info" role="note" style="margin-bottom: 1.5rem; background: rgba(15, 23, 42, 0.04); border-left: 4px solid var(--color-primary, #0f172a); padding: 0.875rem 1rem; border-radius: 4px;">
            <strong>Exact Vehicle Self-Drive Hire (No Driver Provided)</strong>
            <p style="margin: 0.25rem 0 0; font-size: 0.875rem;">Rent reservations are strictly self-drive. Spinellanka does not assign a chauffeur for Rent bookings. The driver must physically present original passport, national driving licence, and International Driving Permit (IDP) at handover. Digital photos or scans are legally void.</p>
          </div>

          <!-- Rental Period -->
          <fieldset class="form-section">
            <legend class="section-title">Rental Period (24-Hour Cycles)</legend>
            <p class="section-desc">Hire periods are calculated in 24-hour cycles in Sri Lanka Time (Asia/Colombo / UTC+05:30) with a {{ $policy->grace_period_minutes }}-minute return grace period.</p>

            <div class="form-row">
              <div class="form-group">
                <label for="pickup_date">Pickup Date <span class="required">*</span></label>
                <input type="date" id="pickup_date" name="pickup_date" value="{{ old('pickup_date', $criteria?->pickupDateTime?->format('Y-m-d') ?? now('Asia/Colombo')->addDays(2)->format('Y-m-d')) }}" min="{{ now('Asia/Colombo')->format('Y-m-d') }}" required class="form-input @error('pickup_date') is-invalid @enderror">
                @error('pickup_date') <span class="form-error">{{ $message }}</span> @enderror
              </div>

              <div class="form-group">
                <label for="pickup_time">Pickup Time (Sri Lanka Time) <span class="required">*</span></label>
                <input type="time" id="pickup_time" name="pickup_time" value="{{ old('pickup_time', $criteria?->pickupDateTime?->format('H:i') ?? '10:00') }}" required class="form-input @error('pickup_time') is-invalid @enderror">
                @error('pickup_time') <span class="form-error">{{ $message }}</span> @enderror
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label for="return_date">Return Date <span class="required">*</span></label>
                <input type="date" id="return_date" name="return_date" value="{{ old('return_date', $criteria?->returnDateTime?->format('Y-m-d') ?? now('Asia/Colombo')->addDays(5)->format('Y-m-d')) }}" min="{{ now('Asia/Colombo')->format('Y-m-d') }}" required class="form-input @error('return_date') is-invalid @enderror">
                @error('return_date') <span class="form-error">{{ $message }}</span> @enderror
              </div>

              <div class="form-group">
                <label for="return_time">Return Time (Sri Lanka Time) <span class="required">*</span></label>
                <input type="time" id="return_time" name="return_time" value="{{ old('return_time', $criteria?->returnDateTime?->format('H:i') ?? '10:00') }}" required class="form-input @error('return_time') is-invalid @enderror">
                @error('return_time') <span class="form-error">{{ $message }}</span> @enderror
              </div>
            </div>
          </fieldset>

          <!-- Handover & Collection Locations -->
          <fieldset class="form-section">
            <legend class="section-title">Handover &amp; Return Locations</legend>
            <p class="section-desc">Choose convenient vehicle collection and return points.</p>

            <div class="form-group">
              <label for="pickup_location_type">Pickup Location Type <span class="required">*</span></label>
              <select id="pickup_location_type" name="pickup_location_type" required class="form-input" onchange="updatePickupText(this.value)">
                <option value="office" {{ old('pickup_location_type', $criteria?->pickupLocationType ?? 'office') === 'office' ? 'selected' : '' }}>Spinellanka Head Office, Colombo 03 (Free Handover)</option>
                <option value="airport" {{ old('pickup_location_type', $criteria?->pickupLocationType) === 'airport' ? 'selected' : '' }}>Bandaranaike International Airport (CMB), Katunayake (Free)</option>
                <option value="hotel" {{ old('pickup_location_type', $criteria?->pickupLocationType) === 'hotel' ? 'selected' : '' }}>Hotel Delivery in Colombo District (${{ number_format($policy->hotel_delivery_fee_minor_units / 100, 2) }} USD)</option>
              </select>
            </div>

            <div class="form-group">
              <label for="pickup_location_text">Pickup Location Specific Address <span class="required">*</span></label>
              <input type="text" id="pickup_location_text" name="pickup_location_text" value="{{ old('pickup_location_text', $criteria?->pickupLocationText ?? 'Spinellanka Head Office, Colombo 03') }}" required class="form-input @error('pickup_location_text') is-invalid @enderror">
              @error('pickup_location_text') <span class="form-error">{{ $message }}</span> @enderror
            </div>

            <div class="form-group">
              <label for="return_location_type">Return Location Type <span class="required">*</span></label>
              <select id="return_location_type" name="return_location_type" required class="form-input" onchange="updateReturnText(this.value)">
                <option value="office" {{ old('return_location_type', $criteria?->returnLocationType ?? 'office') === 'office' ? 'selected' : '' }}>Spinellanka Head Office, Colombo 03 (Free Collection)</option>
                <option value="airport" {{ old('return_location_type', $criteria?->returnLocationType) === 'airport' ? 'selected' : '' }}>Bandaranaike International Airport (CMB), Katunayake (Free)</option>
                <option value="hotel" {{ old('return_location_type', $criteria?->returnLocationType) === 'hotel' ? 'selected' : '' }}>Hotel Collection in Colombo District</option>
              </select>
            </div>

            <div class="form-group">
              <label for="return_location_text">Return Location Specific Address <span class="required">*</span></label>
              <input type="text" id="return_location_text" name="return_location_text" value="{{ old('return_location_text', $criteria?->returnLocationText ?? 'Spinellanka Head Office, Colombo 03') }}" required class="form-input @error('return_location_text') is-invalid @enderror">
              @error('return_location_text') <span class="form-error">{{ $message }}</span> @enderror
            </div>
          </fieldset>

          <!-- Driver Eligibility Verification -->
          <fieldset class="form-section">
            <legend class="section-title">Lead Driver Eligibility Verification</legend>
            <p class="section-desc">We verify legal eligibility before vehicle allocation. Physical original documents will be inspected in person at handover.</p>

            <div class="form-row">
              <div class="form-group">
                <label for="driver_age">Lead Driver Age <span class="required">*</span></label>
                <input type="number" id="driver_age" name="driver_age" value="{{ old('driver_age', $criteria?->driverAge ?? 30) }}" min="{{ $policy->min_driver_age }}" max="{{ $policy->max_driver_age }}" required class="form-input @error('driver_age') is-invalid @enderror" aria-describedby="age-hint">
                <small id="age-hint" class="form-hint">Must be between {{ $policy->min_driver_age }} and {{ $policy->max_driver_age }} years old.</small>
                @error('driver_age') <span class="form-error">{{ $message }}</span> @enderror
              </div>

              <div class="form-group">
                <label for="driver_licence_country">Licence Issuing Country <span class="required">*</span></label>
                <input type="text" id="driver_licence_country" name="driver_licence_country" value="{{ old('driver_licence_country', $criteria?->driverLicenceCountry ?? 'United Kingdom') }}" placeholder="e.g. United Kingdom, Germany, Australia" required class="form-input @error('driver_licence_country') is-invalid @enderror">
                @error('driver_licence_country') <span class="form-error">{{ $message }}</span> @enderror
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label for="driver_licence_years">Years Licence Held <span class="required">*</span></label>
                <input type="number" id="driver_licence_years" name="driver_licence_years" value="{{ old('driver_licence_years', $criteria?->driverLicenceYears ?? 5) }}" min="{{ $policy->min_licence_years }}" max="70" required class="form-input @error('driver_licence_years') is-invalid @enderror" aria-describedby="exp-hint">
                <small id="exp-hint" class="form-hint">Minimum {{ $policy->min_licence_years }} full years required.</small>
                @error('driver_licence_years') <span class="form-error">{{ $message }}</span> @enderror
              </div>

              <div class="form-group" style="display: flex; flex-direction: column; justify-content: flex-end;">
                <label class="checkbox-label" style="display: flex; align-items: flex-start; gap: 0.5rem; cursor: pointer;">
                  <input type="checkbox" id="has_idp" name="has_idp" value="1" {{ old('has_idp', $criteria?->hasIdp ?? true) ? 'checked' : '' }} required style="margin-top: 0.25rem;">
                  <span><strong>Valid IDP or AAC Endorsement Held:</strong> I hold a valid International Driving Permit (1949/1968 convention) or Sri Lankan AAC endorsement.</span>
                </label>
                @error('has_idp') <span class="form-error">{{ $message }}</span> @enderror
              </div>
            </div>

            <!-- Additional Driver Section -->
            <div style="margin-top: 1.5rem; padding-top: 1.5rem; border-top: 1px solid var(--color-border, #e2e8f0);">
              <label class="checkbox-label" style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer; font-weight: 600;">
                <input type="checkbox" id="has_additional_driver" name="has_additional_driver" value="1" {{ old('has_additional_driver', $criteria?->hasAdditionalDriver) ? 'checked' : '' }} onchange="document.getElementById('additional-driver-fields').style.display = this.checked ? 'block' : 'none';">
                <span>Register an Additional Driver (Max 1)</span>
              </label>

              <div id="additional-driver-fields" style="display: {{ old('has_additional_driver', $criteria?->hasAdditionalDriver) ? 'block' : 'none' }}; margin-top: 1rem; padding: 1rem; background: var(--color-surface, #f8fafc); border-radius: 6px;">
                <div class="form-group">
                  <label for="additional_driver_name">Additional Driver Full Name</label>
                  <input type="text" id="additional_driver_name" name="additional_driver_name" value="{{ old('additional_driver_name', $criteria?->additionalDriverName) }}" class="form-input">
                </div>

                <div class="form-row">
                  <div class="form-group">
                    <label for="additional_driver_age">Additional Driver Age</label>
                    <input type="number" id="additional_driver_age" name="additional_driver_age" value="{{ old('additional_driver_age', $criteria?->additionalDriverAge) }}" min="{{ $policy->min_driver_age }}" max="{{ $policy->max_driver_age }}" class="form-input">
                  </div>
                  <div class="form-group">
                    <label for="additional_driver_licence_country">Licence Country</label>
                    <input type="text" id="additional_driver_licence_country" name="additional_driver_licence_country" value="{{ old('additional_driver_licence_country', $criteria?->additionalDriverLicenceCountry) }}" class="form-input">
                  </div>
                </div>

                <div class="form-row">
                  <div class="form-group">
                    <label for="additional_driver_licence_years">Years Licence Held</label>
                    <input type="number" id="additional_driver_licence_years" name="additional_driver_licence_years" value="{{ old('additional_driver_licence_years', $criteria?->additionalDriverLicenceYears) }}" min="{{ $policy->min_licence_years }}" max="70" class="form-input">
                  </div>
                  <div class="form-group" style="display: flex; align-items: flex-end;">
                    <label class="checkbox-label" style="display: flex; align-items: center; gap: 0.5rem; cursor: pointer;">
                      <input type="checkbox" id="additional_driver_has_idp" name="additional_driver_has_idp" value="1" {{ old('additional_driver_has_idp', $criteria?->additionalDriverHasIdp) ? 'checked' : '' }}>
                      <span>Additional driver holds valid IDP</span>
                    </label>
                  </div>
                </div>
              </div>
            </div>

            <!-- Expected Distance -->
            <div class="form-group" style="margin-top: 1.5rem;">
              <label for="expected_distance_km">Expected Travel Distance (Kilometers, Optional)</label>
              <input type="number" id="expected_distance_km" name="expected_distance_km" value="{{ old('expected_distance_km', $criteria?->expectedDistanceKm) }}" placeholder="e.g. 500" class="form-input" aria-describedby="dist-hint">
              <small id="dist-hint" class="form-hint">Each rental includes {{ $policy->daily_mileage_km }} km per rental day cumulatively. Excess kilometers are billed at ${{ number_format($policy->excess_mileage_rate_minor_units / 100, 2) }} USD/km at vehicle return.</small>
            </div>
          </fieldset>

          <div class="form-actions" style="margin-top: 2rem;">
            <button type="submit" class="submit-button" style="width: 100%; min-height: 48px; font-weight: 600;">
              Search Available Self-Drive Fleet &rsaquo;
            </button>
          </div>
        </form>
      </div>

      <!-- Right Column: Policy & Handover Summary -->
      <aside class="booking-side-col">
        <div class="booking-card" style="padding: 1.5rem; background: var(--color-surface, #ffffff); border: 1px solid var(--color-border, #e2e8f0); border-radius: 8px;">
          <h2 class="card-heading" style="font-size: 1.125rem; font-weight: 700; margin-bottom: 1rem;">Self-Drive Rental Terms</h2>
          <ul style="list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 0.875rem; font-size: 0.875rem; color: var(--color-text-muted, #475569);">
            <li style="display: flex; gap: 0.5rem;">
              <span style="color: #0b57d0;">✓</span>
              <span><strong>Driver Age:</strong> {{ $policy->min_driver_age }}–{{ $policy->max_driver_age }} years (min {{ $policy->min_licence_years }} years licence)</span>
            </li>
            <li style="display: flex; gap: 0.5rem;">
              <span style="color: #0b57d0;">✓</span>
              <span><strong>IDP Required:</strong> Valid 1949/1968 IDP or AAC Sri Lankan permit mandatory</span>
            </li>
            <li style="display: flex; gap: 0.5rem;">
              <span style="color: #0b57d0;">✓</span>
              <span><strong>No Document Uploads:</strong> Original physical documents verified in person at handover</span>
            </li>
            <li style="display: flex; gap: 0.5rem;">
              <span style="color: #0b57d0;">✓</span>
              <span><strong>Daily Allowance:</strong> {{ $policy->daily_mileage_km }} km/day cumulative</span>
            </li>
            <li style="display: flex; gap: 0.5rem;">
              <span style="color: #0b57d0;">✓</span>
              <span><strong>Security Deposit:</strong> ${{ number_format($policy->security_deposit_minor_units / 100, 2) }} USD refundable pre-auth</span>
            </li>
            <li style="display: flex; gap: 0.5rem;">
              <span style="color: #0b57d0;">✓</span>
              <span><strong>Insurance:</strong> Comprehensive coverage with ${{ number_format($policy->insurance_excess_minor_units / 100, 2) }} USD excess cap</span>
            </li>
            <li style="display: flex; gap: 0.5rem;">
              <span style="color: #0b57d0;">✓</span>
              <span><strong>Fuel:</strong> Same-to-same return policy</span>
            </li>
          </ul>
        </div>
      </aside>
    </div>
  </div>
  @include('partials.footer')
</main>

<script>
function updatePickupText(type) {
  const el = document.getElementById('pickup_location_text');
  if (type === 'office') el.value = 'Spinellanka Head Office, Colombo 03';
  else if (type === 'airport') el.value = 'Bandaranaike International Airport (CMB), Katunayake';
  else el.value = '';
}
function updateReturnText(type) {
  const el = document.getElementById('return_location_text');
  if (type === 'office') el.value = 'Spinellanka Head Office, Colombo 03';
  else if (type === 'airport') el.value = 'Bandaranaike International Airport (CMB), Katunayake';
  else el.value = '';
}
</script>
@endsection

