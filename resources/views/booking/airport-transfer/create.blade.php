@extends('layouts.app')

@section('title', 'Book Airport Transfer — Spinellanka')
@section('meta_description', 'Reserve an advance chauffeured departure transfer to Bandaranaike International Airport (CMB) with guided pickup timing.')

@section('content')
<main id="main-content" class="booking-page">
  <div class="booking-container">
    <div class="booking-header">
      <nav class="booking-breadcrumbs" aria-label="Breadcrumb">
        <a href="{{ route('home') }}">Home</a> &rsaquo;
        <a href="{{ route('services.show', ['service' => 'airport_transfer']) }}">Airport Transfer</a> &rsaquo;
        <span aria-current="page">Book</span>
      </nav>
      <h1 id="page-title">Airport Transfer Reservation</h1>
      <p class="booking-subtitle">Pre-booked chauffeured departure journey from your hotel or residence to Bandaranaike International Airport (CMB).</p>
      
      <ol class="booking-steps" aria-label="Booking progress">
        <li class="step active" aria-current="step"><span class="step-num">1</span> <span class="step-title">Flight &amp; Origin</span></li>
        <li class="step"><span class="step-num">2</span> <span class="step-title">Select Vehicle</span></li>
        <li class="step"><span class="step-num">3</span> <span class="step-title">Traveler Details</span></li>
        <li class="step"><span class="step-num">4</span> <span class="step-title">Review</span></li>
      </ol>
    </div>

    @if(session('error'))
      <div class="alert alert-error" role="alert">
        {{ session('error') }}
      </div>
    @endif

    @if($errors->any())
      <div class="alert alert-error" role="alert">
        <strong>Please check the following timing or entry details:</strong>
        <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        <p class="error-support-note" style="margin-top: 0.5rem; font-size: 0.875rem;">Need assistance or have an urgent departure? Please <a href="{{ route('contact') }}" style="text-decoration: underline;">contact our support desk</a>.</p>
      </div>
    @endif

    <div class="booking-content-grid">
      <div class="booking-form-card">
        <form action="{{ route('booking.airport-transfer.search') }}" method="POST" class="booking-form" novalidate>
          @csrf

          <!-- Flight Departure Information -->
          <fieldset class="form-section">
            <legend class="section-title">Departure Flight Details</legend>
            <p class="section-desc">We use your departure flight time to calculate a safe, stress-free pickup time from your location.</p>

            <div class="form-row">
              <div class="form-group">
                <label for="airport">Departure Airport</label>
                <select id="airport" name="airport" required class="form-input">
                  <option value="CMB" selected>Bandaranaike International Airport, Katunayake (CMB)</option>
                </select>
                <small class="form-hint">Primary international airport for Colombo and western/southern regions.</small>
              </div>

              <div class="form-group">
                <label for="flight_number">Departure Flight Number <span class="required">*</span></label>
                <input type="text" id="flight_number" name="flight_number" value="{{ old('flight_number', $criteria?->flightNumber) }}" placeholder="e.g. EK651, UL504, QR665" required autocomplete="off" autocapitalize="characters" spellcheck="false" enterkeyhint="next" class="form-input @error('flight_number') is-invalid @enderror" aria-describedby="flight-hint">
                <small id="flight-hint" class="form-hint">Format: 2-letter airline code and number.</small>
                @error('flight_number') <span class="form-error">{{ $message }}</span> @enderror
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label for="departure_date">Flight Departure Date <span class="required">*</span></label>
                <input type="date" id="departure_date" name="departure_date" value="{{ old('departure_date', $criteria?->departureTime?->format('Y-m-d') ?? now('Asia/Colombo')->addDays(2)->format('Y-m-d')) }}" min="{{ now('Asia/Colombo')->format('Y-m-d') }}" required class="form-input @error('departure_date') is-invalid @enderror">
                @error('departure_date') <span class="form-error">{{ $message }}</span> @enderror
              </div>

              <div class="form-group">
                <label for="departure_time">Flight Departure Time (Sri Lanka Time) <span class="required">*</span></label>
                <input type="time" id="departure_time" name="departure_time" value="{{ old('departure_time', $criteria?->departureTime?->format('H:i') ?? '20:00') }}" required class="form-input @error('departure_time') is-invalid @enderror" aria-describedby="dep-time-hint">
                <small id="dep-time-hint" class="form-hint">Scheduled takeoff time in Sri Lanka Local Time (Asia/Colombo / UTC+05:30).</small>
                @error('departure_time') <span class="form-error">{{ $message }}</span> @enderror
              </div>
            </div>
          </fieldset>

          <!-- Origin & Route -->
          <fieldset class="form-section">
            <legend class="section-title">Pickup Origin &amp; Destination</legend>
            <p class="section-desc">Specify your departure pickup location. Katunayake Expressway tolls are included.</p>

            <div class="form-group">
              <label for="origin_text">Pickup Hotel or Address <span class="required">*</span></label>
              <input type="text" id="origin_text" name="origin_text" value="{{ old('origin_text', $criteria?->originText) }}" placeholder="e.g. Galle Fort Heritage Villa, Galle or Cinnamon Grand, Colombo" required autocomplete="street-address" enterkeyhint="next" class="form-input @error('origin_text') is-invalid @enderror" aria-describedby="origin-hint">
              <small id="origin-hint" class="form-hint">Enter hotel name or complete street address. We verify route travel times and road conditions.</small>
              @error('origin_text') <span class="form-error">{{ $message }}</span> @enderror
            </div>

            @if(!empty($places))
              <div class="quick-places" aria-label="Suggested popular pickup origins">
                <span class="quick-label">Popular Pickup Locations:</span>
                @foreach($places as $place)
                  <button
                    type="button"
                    class="quick-pill"
                    data-address="{{ $place->formattedAddress }}"
                    data-place-id="{{ $place->placeId }}"
                    onclick="document.getElementById('origin_text').value = this.dataset.address; document.getElementById('place_id').value = this.dataset.placeId;"
                  >
                    {{ $place->mainText }}
                  </button>
                @endforeach
              </div>
            @endif
            <input type="hidden" id="place_id" name="place_id" value="{{ old('place_id', $criteria?->placeId) }}">

            <div class="form-group">
              <label for="origin_notes">Pickup Notes &amp; Specific Meeting Point (Optional)</label>
              <textarea id="origin_notes" name="origin_notes" rows="2" class="form-input" placeholder="e.g. Hotel main porch, villa gate on lighthouse road, room checkout assistance.">{{ old('origin_notes', $criteria?->originNotes) }}</textarea>
            </div>
          </fieldset>

          <!-- Guided Pickup Timing Policy -->
          <fieldset class="form-section">
            <legend class="section-title">Pickup Timing &amp; Route Guidance</legend>
            
            <div class="guidance-callout-box" role="region" aria-label="Timing Policy Explanation">
              <div class="guidance-header">
                <span class="guidance-icon" aria-hidden="true">⏱</span>
                <div>
                  <strong>Recommended Departure Buffer Standard</strong>
                  <p class="guidance-subtext">To protect your flight check-in, our recommendation automatically includes: <strong>3 hours (180 mins) airport check-in buffer</strong> + estimated drive duration + <strong>45 mins road contingency</strong>.</p>
                </div>
              </div>
            </div>

            <div class="timing-choice-container">
              <label class="radio-card">
                <input type="radio" name="pickup_timing_mode" value="recommended" {{ old('pickup_timing_mode', $criteria?->isUnsafeTime ? 'custom' : 'recommended') === 'recommended' ? 'checked' : '' }} onchange="document.getElementById('custom-timing-box').style.display = 'none';">
                <div class="radio-card-content">
                  <strong>Use Spinellanka Recommended Pickup Time (Recommended &amp; Safest)</strong>
                  <span class="radio-sub">Automatically calculated for safe arrival at CMB 3 hours before flight departure.</span>
                </div>
              </label>

              <label class="radio-card">
                <input type="radio" name="pickup_timing_mode" value="custom" {{ old('pickup_timing_mode', $criteria?->isUnsafeTime ? 'custom' : 'recommended') === 'custom' ? 'checked' : '' }} onchange="document.getElementById('custom-timing-box').style.display = 'block';">
                <div class="radio-card-content">
                  <strong>I Prefer to Choose a Custom Pickup Time</strong>
                  <span class="radio-sub">Adjust your pickup time. Times later than recommended require risk acknowledgment.</span>
                </div>
              </label>
            </div>

            <div id="custom-timing-box" class="custom-timing-fields" style="{{ old('pickup_timing_mode', $criteria?->isUnsafeTime ? 'custom' : 'recommended') === 'custom' ? 'display: block;' : 'display: none;' }}">
              <div class="form-row">
                <div class="form-group">
                  <label for="custom_pickup_date">Preferred Pickup Date</label>
                  <input type="date" id="custom_pickup_date" name="custom_pickup_date" value="{{ old('custom_pickup_date', $criteria?->chosenPickupTime?->format('Y-m-d') ?? now('Asia/Colombo')->addDays(2)->format('Y-m-d')) }}" class="form-input">
                </div>

                <div class="form-group">
                  <label for="custom_pickup_time">Preferred Pickup Time (Asia/Colombo)</label>
                  <input type="time" id="custom_pickup_time" name="custom_pickup_time" value="{{ old('custom_pickup_time', $criteria?->chosenPickupTime?->format('H:i') ?? '15:00') }}" class="form-input" aria-describedby="custom-time-hint">
                  <small id="custom-time-hint" class="form-hint">Latest cutoff: Flight departure minus estimated drive minus 90 min.</small>
                </div>
              </div>

              <!-- Unsafe Time Warning Banner -->
              <div class="warning-banner" role="alert">
                <div class="warning-title">⚠️ Missed-Flight Risk Warning</div>
                <p class="warning-text">Your selected pickup time is later than our recommended time. Arriving less than 3 hours before an international flight significantly increases the risk of missing check-in or baggage cutoffs due to traffic or airport queues.</p>
                
                <div class="form-checkbox-group">
                  <label class="checkbox-label @error('warning_accepted') is-invalid @enderror">
                    <input type="checkbox" name="warning_accepted" value="1" {{ old('warning_accepted', $criteria?->warningAccepted) ? 'checked' : '' }}>
                    <span><strong>Required Acknowledgment:</strong> I acknowledge that my chosen pickup time provides less than the recommended buffer and I accept personal responsibility for my flight departure timing. <span class="required">*</span></span>
                  </label>
                  @error('warning_accepted') <span class="form-error">{{ $message }}</span> @enderror
                </div>
              </div>
            </div>
          </fieldset>

          <!-- Party Size & Extras -->
          <fieldset class="form-section">
            <legend class="section-title">Party Size &amp; Luggage</legend>

            <div class="form-row">
              <div class="form-group">
                <label for="passenger_count">Passengers <span class="required">*</span></label>
                <input type="number" id="passenger_count" name="passenger_count" value="{{ old('passenger_count', $criteria?->passengerCount ?? 2) }}" min="1" max="10" inputmode="numeric" enterkeyhint="next" required class="form-input @error('passenger_count') is-invalid @enderror">
                <small class="form-hint">Total travelers including children.</small>
                @error('passenger_count') <span class="form-error">{{ $message }}</span> @enderror
              </div>

              <div class="form-group">
                <label for="luggage_count">Luggage Bags (Large / Check-in) <span class="required">*</span></label>
                <input type="number" id="luggage_count" name="luggage_count" value="{{ old('luggage_count', $criteria?->luggageCount ?? 2) }}" min="0" max="10" inputmode="numeric" enterkeyhint="done" required class="form-input @error('luggage_count') is-invalid @enderror">
                <small class="form-hint">Check-in luggage bags.</small>
                @error('luggage_count') <span class="form-error">{{ $message }}</span> @enderror
              </div>
            </div>

            <div class="form-group">
              <label for="special_instructions">Luggage Details or Accessibility Notes (Optional)</label>
              <textarea id="special_instructions" name="special_instructions" rows="2" class="form-input" placeholder="e.g. Golf bags, surfboards, infant child seat requested.">{{ old('special_instructions', $criteria?->specialInstructions) }}</textarea>
            </div>
          </fieldset>

          <div class="form-actions">
            <button type="submit" class="primary-button">Find Available Transfer Vehicles &rsaquo;</button>
          </div>
        </form>
      </div>

      <aside class="booking-sidebar">
        <div class="info-card">
          <h2 class="card-heading" style="font-size: 1.125rem;">Airport Transfer Standards</h2>
          <ul class="benefit-list">
            <li><strong>Exact Vehicle Guarantee:</strong> You reserve the specific vehicle model and registration plate shown.</li>
            <li><strong>Chauffeured Service:</strong> Dedicated, vetted professional driver assigned to your journey.</li>
            <li><strong>Highway Tolls Included:</strong> All Katunayake Expressway toll fees and airport departure drop-off charges are fully included.</li>
            <li><strong>Punctual Hotel Pickup:</strong> Driver arrives at your hotel porch 15 minutes prior to scheduled departure.</li>
            <li><strong>Staff Route Review:</strong> Our dispatch desk monitors traffic and weather conditions prior to pickup.</li>
          </ul>
        </div>
      </aside>
    </div>
  </div>
  @include('partials.footer')
</main>
@endsection

