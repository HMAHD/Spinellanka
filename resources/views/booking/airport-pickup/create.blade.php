@extends('layouts.app')

@section('title', 'Book Airport Pickup — Spinellanka')
@section('meta_description', 'Reserve an advance airport pickup from Bandaranaike International Airport (CMB) with dedicated chauffeured meet-and-greet.')

@section('content')
<main id="main-content" class="booking-page">
  <div class="booking-container">
    <div class="booking-header">
      <nav class="booking-breadcrumbs" aria-label="Breadcrumb">
        <a href="{{ route('home') }}">Home</a> &rsaquo;
        <a href="{{ route('services.show', ['service' => 'airport_pickup']) }}">Airport Pickup</a> &rsaquo;
        <span aria-current="page">Book</span>
      </nav>
      <h1 id="page-title">Airport Pickup Reservation</h1>
      <p class="booking-subtitle">Advance chauffeured airport arrival service from Bandaranaike International Airport (CMB).</p>
      
      <ol class="booking-steps" aria-label="Booking progress">
        <li class="step active" aria-current="step"><span class="step-num">1</span> <span class="step-title">Flight & Destination</span></li>
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

    <div class="booking-content-grid">
      <div class="booking-form-card">
        <form action="{{ route('booking.airport-pickup.search') }}" method="POST" class="booking-form" novalidate>
          @csrf

          <fieldset class="form-section">
            <legend class="section-title">Flight Arrival Information</legend>
            <p class="section-desc">Our dispatch team tracks your flight number to adjust for arrival customs and baggage delays.</p>

            <div class="form-row">
              <div class="form-group">
                <label for="airport">Arrival Airport</label>
                <select id="airport" name="airport" required class="form-input">
                  <option value="CMB" selected>Bandaranaike International Airport, Katunayake (CMB)</option>
                </select>
                <small class="form-hint">Colombo primary international gateway.</small>
              </div>

              <div class="form-group">
                <label for="flight_number">Flight Number <span class="required">*</span></label>
                <input type="text" id="flight_number" name="flight_number" value="{{ old('flight_number', $criteria?->flightNumber) }}" placeholder="e.g. UL504, EK650, QR664" required autocomplete="off" autocapitalize="characters" spellcheck="false" enterkeyhint="next" class="form-input @error('flight_number') is-invalid @enderror" aria-describedby="flight-hint">
                <small id="flight-hint" class="form-hint">Format: 2-letter airline code and number.</small>
                @error('flight_number') <span class="form-error">{{ $message }}</span> @enderror
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label for="arrival_date">Scheduled Arrival Date <span class="required">*</span></label>
                <input type="date" id="arrival_date" name="arrival_date" value="{{ old('arrival_date', $criteria?->arrivalTime?->format('Y-m-d') ?? now('Asia/Colombo')->addDays(2)->format('Y-m-d')) }}" min="{{ now('Asia/Colombo')->format('Y-m-d') }}" required class="form-input @error('arrival_date') is-invalid @enderror">
                @error('arrival_date') <span class="form-error">{{ $message }}</span> @enderror
              </div>

              <div class="form-group">
                <label for="arrival_time">Scheduled Arrival Time (Sri Lanka Time / Asia/Colombo) <span class="required">*</span></label>
                <input type="time" id="arrival_time" name="arrival_time" value="{{ old('arrival_time', $criteria?->arrivalTime?->format('H:i') ?? '14:30') }}" required class="form-input @error('arrival_time') is-invalid @enderror" aria-describedby="time-hint">
                <small id="time-hint" class="form-hint">All flight times are coordinated in Sri Lanka Local Time (UTC+05:30).</small>
                @error('arrival_time') <span class="form-error">{{ $message }}</span> @enderror
              </div>
            </div>
          </fieldset>

          <fieldset class="form-section">
            <legend class="section-title">Drop-off Destination & Party Size</legend>

            <div class="form-group">
              <label for="destination_text">Destination Hotel or Address <span class="required">*</span></label>
              <input type="text" id="destination_text" name="destination_text" value="{{ old('destination_text', $criteria?->destinationText) }}" placeholder="e.g. Galle Face Hotel, Colombo 03 or Jetwing Lighthouse, Galle" required autocomplete="street-address" enterkeyhint="next" class="form-input @error('destination_text') is-invalid @enderror" aria-describedby="dest-hint">
              <small id="dest-hint" class="form-hint">Type your destination or select a known hub below. We preserve exact text and verify the route.</small>
              @error('destination_text') <span class="form-error">{{ $message }}</span> @enderror
            </div>

            @if(!empty($places))
              <div class="quick-places" aria-label="Suggested popular destinations">
                <span class="quick-label">Popular Destinations:</span>
                @foreach($places as $place)
                  <button type="button" class="quick-pill" onclick="document.getElementById('destination_text').value='{{ $place->formattedAddress }}'; document.getElementById('place_id').value='{{ $place->placeId }}';">
                    {{ $place->mainText }}
                  </button>
                @endforeach
              </div>
            @endif
            <input type="hidden" id="place_id" name="place_id" value="{{ old('place_id', $criteria?->placeId) }}">

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
                <small class="form-hint">Standard 23kg check-in suitcases.</small>
                @error('luggage_count') <span class="form-error">{{ $message }}</span> @enderror
              </div>
            </div>

            <div class="form-group">
              <label for="destination_notes">Special Instructions or Landmark Notes (Optional)</label>
              <textarea id="destination_notes" name="destination_notes" rows="2" class="form-input" placeholder="e.g. Flight arrives late night; please have driver wait near Exit Gate B.">{{ old('destination_notes', $criteria?->destinationNotes) }}</textarea>
            </div>

            <div class="form-checkbox-group">
              <label class="checkbox-label">
                <input type="checkbox" name="meet_and_greet" value="1" checked>
                <span>Include Airport Terminal Meet &amp; Greet with Name Board (Complimentary)</span>
              </label>
            </div>

            <div class="form-group">
              <label for="name_board_text">Name to Display on Welcome Board</label>
              <input type="text" id="name_board_text" name="name_board_text" value="{{ old('name_board_text', $criteria?->nameBoardText) }}" placeholder="e.g. Mr. David Smith" class="form-input">
            </div>
          </fieldset>

          <div class="form-actions">
            <button type="submit" class="primary-button">Find Available Vehicles &rsaquo;</button>
          </div>
        </form>
      </div>

      <aside class="booking-sidebar">
        <div class="info-card">
          <h3>The Spinellanka Airport Standard</h3>
          <ul class="benefit-list">
            <li><strong>Exact Vehicle Guarantee:</strong> The exact vehicle model and plate you select is reserved exclusively for you.</li>
            <li><strong>Chauffeured Meet &amp; Greet:</strong> Professional English-speaking driver waiting inside arrivals with your name board.</li>
            <li><strong>Delay Protection:</strong> 90 minutes of complimentary waiting included from actual flight touchdown.</li>
            <li><strong>Manual Review:</strong> Staff re-checks flight schedule and contacts you directly before confirming.</li>
          </ul>
        </div>
      </aside>
    </div>
  </div>
</main>
@endsection

