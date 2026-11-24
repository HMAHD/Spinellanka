@extends('layouts.app')

@section('title', 'Book a Ride — Spinellanka')
@section('meta_description', 'Book a chauffeured point-to-point journey or multi-stop ride across Sri Lanka with exact-vehicle selection and transparent pricing.')

@section('content')
<main id="main-content" class="booking-page">
  <div class="booking-container">
    <div class="booking-header">
      <nav class="booking-breadcrumbs" aria-label="Breadcrumb">
        <a href="{{ route('home') }}">Home</a> &rsaquo;
        <a href="{{ route('services.show', ['service' => 'ride']) }}">Chauffeured Ride</a> &rsaquo;
        <span aria-current="page">Book</span>
      </nav>
      <h1 id="page-title">Chauffeured Ride Reservation</h1>
      <p class="booking-subtitle">Point-to-point transfers and custom multi-stop itineraries across Sri Lanka with dedicated, verified professional chauffeurs.</p>

      <ol class="booking-steps" aria-label="Booking progress">
        <li class="step active" aria-current="step"><span class="step-num">1</span> <span class="step-title">Route &amp; Timing</span></li>
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
        <strong>Please check the following itinerary or entry details:</strong>
        <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
        <p class="error-support-note" style="margin-top: 0.5rem; font-size: 0.875rem;">Need assistance or have a complex custom itinerary? Please <a href="{{ route('contact') }}" style="text-decoration: underline;">contact our operations desk</a>.</p>
      </div>
    @endif

    @php
      // Extract stops from draft (No-JS actions), session criteria, or old input
      $stopsList = old('stops', $draft['stops'] ?? (
          $criteria && !empty($criteria->stops) 
              ? array_map(fn($s) => ['location_text' => $s->locationText, 'waiting_minutes' => $s->plannedWaitingMinutes], $criteria->stops)
              : []
      ));
      if (!is_array($stopsList)) {
          $stopsList = [];
      }
      $hasReturnOld = old('has_return', isset($draft['has_return']) ? (bool) $draft['has_return'] : ($criteria?->hasReturn ?? false));
      $pickupDateVal = old('pickup_date', $draft['pickup_date'] ?? ($criteria?->pickupTime?->format('Y-m-d') ?? now('Asia/Colombo')->addDays(1)->format('Y-m-d')));
      $pickupTimeVal = old('pickup_time', $draft['pickup_time'] ?? ($criteria?->pickupTime?->format('H:i') ?? '09:00'));
      $originVal = old('origin_text', $draft['origin_text'] ?? ($criteria?->originText ?? ''));
      $destVal = old('destination_text', $draft['destination_text'] ?? ($criteria?->destinationText ?? ''));
      $passCountVal = old('passenger_count', $draft['passenger_count'] ?? ($criteria?->passengerCount ?? 2));
      $luggageCountVal = old('luggage_count', $draft['luggage_count'] ?? ($criteria?->luggageCount ?? 2));
      $returnDateVal = old('return_date', $draft['return_date'] ?? ($criteria?->returnPickupTime?->format('Y-m-d') ?? now('Asia/Colombo')->addDays(1)->format('Y-m-d')));
      $returnTimeVal = old('return_time', $draft['return_time'] ?? ($criteria?->returnPickupTime?->format('H:i') ?? '16:00'));
      $notesVal = old('notes', $draft['notes'] ?? ($criteria?->specialInstructions ?? ''));
    @endphp

    <div class="booking-content-grid">
      <div class="booking-form-card">
        <form action="{{ route('booking.ride.search') }}" method="POST" id="ride-booking-form" class="booking-form" novalidate>
          @csrf

          <!-- Screen-Reader Reorder Announcement Live Region -->
          <div id="reorder-status" role="status" aria-live="polite" class="sr-only"></div>

          <!-- Section: Journey Origin & Final Destination -->
          <fieldset class="form-section">
            <legend class="section-title">Pickup Origin &amp; Destination</legend>
            <p class="section-desc">Specify where your chauffeured journey begins and your final destination. Southern &amp; Central Expressway tolls are included.</p>

            <div class="form-group">
              <label for="origin_text">Pickup Origin / Hotel Address <span class="required">*</span></label>
              <input type="text" id="origin_text" name="origin_text" value="{{ $originVal }}" placeholder="e.g. Cinnamon Grand, Colombo 03 or Bandaranaike Airport" required autocomplete="street-address" enterkeyhint="next" class="form-input @error('origin_text') is-invalid @enderror" aria-describedby="origin-hint">
              <small id="origin-hint" class="form-hint">Enter hotel name, landmark, or street address. Chauffeur arrives 15 minutes before scheduled pickup.</small>
              @error('origin_text') <span class="form-error">{{ $message }}</span> @enderror
            </div>

            <!-- Dynamic Stops Builder (Progressive Enhancement) -->
            <div class="stops-builder-container" id="stops-builder-section" style="margin: 1.5rem 0; padding: 1.25rem; background: var(--color-surface-sunken, #f8fafc); border: 1px solid var(--color-border, #e2e8f0); border-radius: 8px;">
              <div class="stops-builder-header" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1rem; flex-wrap: wrap; gap: 0.5rem;">
                <div>
                  <h3 style="font-size: 1rem; font-weight: 600; margin: 0; color: var(--color-text-primary, #0f172a);">
                    En-Route Stops (Optional, Max 10)
                  </h3>
                  <small style="color: var(--color-text-secondary, #64748b);">Add sightseeing stops, meal breaks, or intermediate passenger pickups.</small>
                </div>
                <button type="submit" name="action" value="add_stop" id="add-stop-btn" class="secondary-button" style="padding: 0.4rem 0.85rem; font-size: 0.875rem;" {{ count($stopsList) >= 10 ? 'disabled' : '' }}>
                  + Add Intermediate Stop
                </button>
              </div>

              <div id="stops-container" class="stops-list" role="list" aria-label="Intermediate stops list">
                @forelse($stopsList as $idx => $stop)
                  <div class="stop-row" data-index="{{ $idx }}" role="listitem" style="display: flex; gap: 0.75rem; align-items: flex-start; margin-bottom: 0.75rem; padding: 0.75rem; background: var(--color-surface, #ffffff); border: 1px solid var(--color-border, #e2e8f0); border-radius: 6px; flex-wrap: wrap;">
                    <span class="stop-badge" aria-label="Stop {{ $idx + 1 }}" style="display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%; background: var(--color-brand-surface, #e0f2fe); color: var(--color-brand, #0284c7); font-weight: 700; font-size: 0.8rem; flex-shrink: 0; margin-top: 0.25rem;">
                      {{ $idx + 1 }}
                    </span>

                    <div style="flex: 1; min-width: 200px;">
                      <label for="stop_loc_{{ $idx }}" class="sr-only">Stop {{ $idx + 1 }} Location</label>
                      <input type="text" id="stop_loc_{{ $idx }}" name="stops[{{ $idx }}][location_text]" value="{{ $stop['location_text'] ?? '' }}" placeholder="Stop address or landmark (e.g. Bentota Beach)" class="form-input" style="width: 100%;" required>
                    </div>

                    <div style="width: 140px; flex-shrink: 0;">
                      <label for="stop_wait_{{ $idx }}" class="sr-only">Stop {{ $idx + 1 }} Waiting Duration</label>
                      <select id="stop_wait_{{ $idx }}" name="stops[{{ $idx }}][waiting_minutes]" class="form-input" style="width: 100%;">
                        <option value="0" {{ ($stop['waiting_minutes'] ?? 0) == 0 ? 'selected' : '' }}>No waiting (Drop/Pickup)</option>
                        <option value="15" {{ ($stop['waiting_minutes'] ?? 0) == 15 ? 'selected' : '' }}>15 min</option>
                        <option value="30" {{ ($stop['waiting_minutes'] ?? 0) == 30 ? 'selected' : '' }}>30 min</option>
                        <option value="60" {{ ($stop['waiting_minutes'] ?? 0) == 60 ? 'selected' : '' }}>1 hour</option>
                        <option value="90" {{ ($stop['waiting_minutes'] ?? 0) == 90 ? 'selected' : '' }}>1.5 hours</option>
                        <option value="120" {{ ($stop['waiting_minutes'] ?? 0) == 120 ? 'selected' : '' }}>2 hours</option>
                        <option value="180" {{ ($stop['waiting_minutes'] ?? 0) == 180 ? 'selected' : '' }}>3 hours</option>
                        <option value="240" {{ ($stop['waiting_minutes'] ?? 0) == 240 ? 'selected' : '' }}>4 hours</option>
                        <option value="300" {{ ($stop['waiting_minutes'] ?? 0) == 300 ? 'selected' : '' }}>5 hours (Max)</option>
                      </select>
                    </div>

                    <!-- Accessible Reorder & Removal Controls -->
                    <div class="stop-actions" style="display: flex; gap: 0.35rem; align-items: center;">
                      <button type="submit" name="action" value="move_up_{{ $idx }}" class="icon-button stop-move-up" aria-label="Move stop {{ $idx + 1 }} up" {{ $idx === 0 ? 'disabled' : '' }} style="padding: 0.3rem 0.5rem; font-size: 0.8rem; border: 1px solid var(--color-border, #cbd5e1); border-radius: 4px; background: white;">
                        &uarr;
                      </button>
                      <button type="submit" name="action" value="move_down_{{ $idx }}" class="icon-button stop-move-down" aria-label="Move stop {{ $idx + 1 }} down" {{ $idx === count($stopsList) - 1 ? 'disabled' : '' }} style="padding: 0.3rem 0.5rem; font-size: 0.8rem; border: 1px solid var(--color-border, #cbd5e1); border-radius: 4px; background: white;">
                        &darr;
                      </button>
                      <button type="submit" name="action" value="remove_stop_{{ $idx }}" class="icon-button stop-remove" aria-label="Remove stop {{ $idx + 1 }}" style="padding: 0.3rem 0.5rem; font-size: 0.8rem; border: 1px solid #fecaca; border-radius: 4px; background: #fff5f5; color: #dc2626;">
                        &times;
                      </button>
                    </div>
                  </div>
                @empty
                  <p id="no-stops-msg" style="margin: 0; font-size: 0.875rem; color: var(--color-text-secondary, #64748b); font-style: italic;">
                    Direct journey selected (no intermediate stops). Click "+ Add Intermediate Stop" if you wish to pause along the route.
                  </p>
                @endforelse
              </div>

              <small style="display: block; margin-top: 0.75rem; font-size: 0.75rem; color: var(--color-text-muted, #94a3b8);">
                * Cumulative waiting across all stops is capped at 480 minutes (8 hours). Chauffeur stays with vehicle.
              </small>
            </div>

            <div class="form-group">
              <label for="destination_text">Final Destination / Drop-off Address <span class="required">*</span></label>
              <input type="text" id="destination_text" name="destination_text" value="{{ $destVal }}" placeholder="e.g. Amangalla, Galle Fort or Sigiriya Village Hotel" required autocomplete="street-address" enterkeyhint="next" class="form-input @error('destination_text') is-invalid @enderror" aria-describedby="dest-hint">
              <small id="dest-hint" class="form-hint">Final drop-off point where outbound journey concludes.</small>
              @error('destination_text') <span class="form-error">{{ $message }}</span> @enderror
            </div>
          </fieldset>

          <!-- Section: Schedule & Party Size -->
          <fieldset class="form-section">
            <legend class="section-title">Schedule &amp; Party Size</legend>
            <p class="section-desc">Advance booking requires at least 6 hours notice before scheduled pickup.</p>

            <div class="form-row">
              <div class="form-group">
                <label for="pickup_date">Pickup Date <span class="required">*</span></label>
                <input type="date" id="pickup_date" name="pickup_date" value="{{ $pickupDateVal }}" min="{{ now('Asia/Colombo')->format('Y-m-d') }}" required class="form-input @error('pickup_date') is-invalid @enderror">
                @error('pickup_date') <span class="form-error">{{ $message }}</span> @enderror
              </div>

              <div class="form-group">
                <label for="pickup_time">Pickup Time (Sri Lanka Time) <span class="required">*</span></label>
                <input type="time" id="pickup_time" name="pickup_time" value="{{ $pickupTimeVal }}" required class="form-input @error('pickup_time') is-invalid @enderror" aria-describedby="pickup-time-hint">
                <small id="pickup-time-hint" class="form-hint">Time chauffeur should arrive at origin (Asia/Colombo / UTC+05:30).</small>
                @error('pickup_time') <span class="form-error">{{ $message }}</span> @enderror
              </div>
            </div>

            <div class="form-row">
              <div class="form-group">
                <label for="passenger_count">Passengers (Adults &amp; Children) <span class="required">*</span></label>
                <input type="number" id="passenger_count" name="passenger_count" value="{{ $passCountVal }}" min="1" max="10" required class="form-input @error('passenger_count') is-invalid @enderror">
                <small class="form-hint">Seats required in vehicle.</small>
                @error('passenger_count') <span class="form-error">{{ $message }}</span> @enderror
              </div>

              <div class="form-group">
                <label for="luggage_count">Luggage / Suitcases <span class="required">*</span></label>
                <input type="number" id="luggage_count" name="luggage_count" value="{{ $luggageCountVal }}" min="0" max="10" required class="form-input @error('luggage_count') is-invalid @enderror">
                <small class="form-hint">Standard 20kg check-in suitcases.</small>
                @error('luggage_count') <span class="form-error">{{ $message }}</span> @enderror
              </div>
            </div>
          </fieldset>

          <!-- Section: Optional Separately Timed Return Leg -->
          <fieldset class="form-section">
            <legend class="section-title">Return Journey (Optional)</legend>
            <p class="section-desc">Lock the exact same vehicle and dedicated permanent driver for a scheduled return leg.</p>

            <div class="form-checkbox-group" style="margin-bottom: 1rem;">
              <input type="hidden" name="has_return" value="0">
              <label class="checkbox-label">
                <input type="checkbox" id="has_return" name="has_return" value="1" {{ $hasReturnOld ? 'checked' : '' }} onchange="document.getElementById('return-leg-fields').style.display = this.checked ? 'block' : 'none'">
                <span><strong>Schedule a return journey</strong> (Outbound destination back to origin)</span>
              </label>
            </div>

            <div id="return-leg-fields" style="{{ $hasReturnOld ? 'display: block;' : 'display: none;' }}; padding: 1rem; background: var(--color-surface-sunken, #f8fafc); border: 1px solid var(--color-border, #e2e8f0); border-radius: 8px;">
              <div class="form-row">
                <div class="form-group">
                  <label for="return_date">Return Pickup Date <span class="required">*</span></label>
                  <input type="date" id="return_date" name="return_date" value="{{ $returnDateVal }}" min="{{ now('Asia/Colombo')->format('Y-m-d') }}" class="form-input @error('return_date') is-invalid @enderror">
                  @error('return_date') <span class="form-error">{{ $message }}</span> @enderror
                </div>

                <div class="form-group">
                  <label for="return_time">Return Pickup Time <span class="required">*</span></label>
                  <input type="time" id="return_time" name="return_time" value="{{ $returnTimeVal }}" class="form-input @error('return_time') is-invalid @enderror" aria-describedby="return-time-hint">
                  <small id="return-time-hint" class="form-hint">Must be at least 60 minutes after outbound arrival + planned waiting.</small>
                  @error('return_time') <span class="form-error">{{ $message }}</span> @enderror
                </div>
              </div>
              <p style="margin: 0.5rem 0 0; font-size: 0.8rem; color: var(--color-text-secondary, #64748b);">
                * Same-vehicle policy: Outbound and return legs are locked together atomically. The vehicle is not reserved for between-leg touring unless continuous waiting is booked.
              </p>
            </div>
          </fieldset>

          <!-- Section: Special Instructions & Notes -->
          <fieldset class="form-section">
            <legend class="section-title">Special Notes &amp; Requests</legend>
            <div class="form-group">
              <label for="notes">Itinerary Notes or Chauffeur Instructions (Optional)</label>
              <textarea id="notes" name="notes" rows="3" class="form-input" placeholder="e.g. Child seat requested, excess surfboard baggage, preference for coastal expressway.">{{ $notesVal }}</textarea>
            </div>
          </fieldset>

          <div class="form-actions" style="margin-top: 1.5rem;">
            <button type="submit" name="action" value="search" class="primary-button submit-btn">
              Find Available Vehicles &rarr;
            </button>
          </div>
        </form>
      </div>

      <!-- Sidebar: Route Policy & Inclusions -->
      <aside class="booking-sidebar">
        <div class="sidebar-card">
          <h2 class="sidebar-title">What is Included</h2>
          <ul class="policy-list">
            <li><strong>Exact Vehicle:</strong> You select the exact unit (make, model, license plate) with verified luggage capacity.</li>
            <li><strong>Dedicated Chauffeur:</strong> English-speaking permanent chauffeur vetted by Spinellanka operations.</li>
            <li><strong>Expressway Tolls Included:</strong> Southern (E01), Outer Circular (E02), and Katunayake (E03) tolls included in base rate.</li>
            <li><strong>Grace Period:</strong> 45-minute courtesy waiting at pickup origin before no-show fee applies.</li>
            <li><strong>Transparent Pricing:</strong> Immediate itemized estimate or seamless Custom Quote review for non-standard routes.</li>
          </ul>
        </div>

        <div class="sidebar-card" style="margin-top: 1.25rem;">
          <h2 class="sidebar-title">Exclusions Notice</h2>
          <p style="font-size: 0.875rem; color: var(--color-text-secondary, #64748b); line-height: 1.5; margin: 0;">
            Site entrance tickets (e.g. Sigiriya, National Parks), passenger meal costs, and commercial location parking fees are payable directly on-site by travelers.
          </p>
        </div>
      </aside>
    </div>
  </div>
</main>

<script>
// Progressive Enhancement: Client-side dynamic stops addition without page reload
document.addEventListener('DOMContentLoaded', function () {
  const addBtn = document.getElementById('add-stop-btn');
  const container = document.getElementById('stops-container');
  const noStopsMsg = document.getElementById('no-stops-msg');
  const statusRegion = document.getElementById('reorder-status');

  if (!addBtn || !container) return;

  // Intercept Add Stop button for JS-enabled users
  addBtn.addEventListener('click', function (e) {
    const currentRows = container.querySelectorAll('.stop-row');
    if (currentRows.length >= 10) return;

    e.preventDefault();
    if (noStopsMsg) noStopsMsg.style.display = 'none';

    const newIndex = currentRows.length;
    const row = document.createElement('div');
    row.className = 'stop-row';
    row.dataset.index = newIndex;
    row.setAttribute('role', 'listitem');
    row.style.cssText = 'display: flex; gap: 0.75rem; align-items: flex-start; margin-bottom: 0.75rem; padding: 0.75rem; background: var(--color-surface, #ffffff); border: 1px solid var(--color-border, #e2e8f0); border-radius: 6px; flex-wrap: wrap;';

    row.innerHTML = `
      <span class="stop-badge" aria-label="Stop ${newIndex + 1}" style="display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 50%; background: var(--color-brand-surface, #e0f2fe); color: var(--color-brand, #0284c7); font-weight: 700; font-size: 0.8rem; flex-shrink: 0; margin-top: 0.25rem;">
        ${newIndex + 1}
      </span>
      <div style="flex: 1; min-width: 200px;">
        <label for="stop_loc_${newIndex}" class="sr-only">Stop ${newIndex + 1} Location</label>
        <input type="text" id="stop_loc_${newIndex}" name="stops[${newIndex}][location_text]" placeholder="Stop address or landmark" class="form-input" style="width: 100%;" required>
      </div>
      <div style="width: 140px; flex-shrink: 0;">
        <label for="stop_wait_${newIndex}" class="sr-only">Stop ${newIndex + 1} Waiting Duration</label>
        <select id="stop_wait_${newIndex}" name="stops[${newIndex}][waiting_minutes]" class="form-input" style="width: 100%;">
          <option value="0">No waiting</option>
          <option value="15">15 min</option>
          <option value="30">30 min</option>
          <option value="60">1 hour</option>
          <option value="90">1.5 hours</option>
          <option value="120">2 hours</option>
          <option value="180">3 hours</option>
          <option value="240">4 hours</option>
          <option value="300">5 hours</option>
        </select>
      </div>
      <div class="stop-actions" style="display: flex; gap: 0.35rem; align-items: center;">
        <button type="button" class="icon-button js-move-up" aria-label="Move stop up" style="padding: 0.3rem 0.5rem; font-size: 0.8rem; border: 1px solid var(--color-border, #cbd5e1); border-radius: 4px; background: white;">&uarr;</button>
        <button type="button" class="icon-button js-move-down" aria-label="Move stop down" style="padding: 0.3rem 0.5rem; font-size: 0.8rem; border: 1px solid var(--color-border, #cbd5e1); border-radius: 4px; background: white;">&darr;</button>
        <button type="button" class="icon-button js-remove" aria-label="Remove stop" style="padding: 0.3rem 0.5rem; font-size: 0.8rem; border: 1px solid #fecaca; border-radius: 4px; background: #fff5f5; color: #dc2626;">&times;</button>
      </div>
    `;

    container.appendChild(row);
    reindexStops();
    if (statusRegion) statusRegion.textContent = `Stop ${newIndex + 1} added.`;
    row.querySelector('input')?.focus();

    if (container.querySelectorAll('.stop-row').length >= 10) {
      addBtn.disabled = true;
    }
  });

  // Event delegation for JS move and remove buttons
  container.addEventListener('click', function (e) {
    const row = e.target.closest('.stop-row');
    if (!row) return;

    if (e.target.closest('.js-remove')) {
      e.preventDefault();
      row.remove();
      reindexStops();
      if (statusRegion) statusRegion.textContent = 'Stop removed.';
      if (container.querySelectorAll('.stop-row').length < 10) {
        addBtn.disabled = false;
      }
      if (container.querySelectorAll('.stop-row').length === 0 && noStopsMsg) {
        noStopsMsg.style.display = 'block';
      }
    } else if (e.target.closest('.js-move-up')) {
      e.preventDefault();
      const prev = row.previousElementSibling;
      if (prev && prev.classList.contains('stop-row')) {
        container.insertBefore(row, prev);
        reindexStops();
        if (statusRegion) statusRegion.textContent = 'Stop moved up.';
        row.querySelector('.js-move-up')?.focus();
      }
    } else if (e.target.closest('.js-move-down')) {
      e.preventDefault();
      const next = row.nextElementSibling;
      if (next && next.classList.contains('stop-row')) {
        container.insertBefore(next, row);
        reindexStops();
        if (statusRegion) statusRegion.textContent = 'Stop moved down.';
        row.querySelector('.js-move-down')?.focus();
      }
    }
  });

  function reindexStops() {
    const rows = container.querySelectorAll('.stop-row');
    rows.forEach((r, i) => {
      r.dataset.index = i;
      const badge = r.querySelector('.stop-badge');
      if (badge) {
        badge.textContent = i + 1;
        badge.setAttribute('aria-label', `Stop ${i + 1}`);
      }
      const locInput = r.querySelector('input[type="text"]');
      if (locInput) {
        locInput.id = `stop_loc_${i}`;
        locInput.name = `stops[${i}][location_text]`;
      }
      const waitSelect = r.querySelector('select');
      if (waitSelect) {
        waitSelect.id = `stop_wait_${i}`;
        waitSelect.name = `stops[${i}][waiting_minutes]`;
      }
    });
  }
});
</script>
@endsection

