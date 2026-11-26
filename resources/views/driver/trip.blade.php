@extends('layouts.driver')

@section('title', "Trip {$trip->reference} · {$trip->service_type_label}")

@section('content')
<article class="trip-card">
  <!-- Reference & Service Badge Header -->
  <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 1.25rem;">
    <div>
      <span style="display: inline-block; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin-bottom: 0.25rem;">
        Booking Reference
      </span>
      <div style="font-size: 1.375rem; font-weight: 800; color: #0C1445; letter-spacing: -0.02em;">
        {{ $trip->reference }}
      </div>
    </div>
    <span style="display: inline-flex; align-items: center; gap: 0.375rem; background-color: #f1f5f9; color: #1e293b; border: 1px solid #cbd5e1; border-radius: 9999px; padding: 0.375rem 0.75rem; font-size: 0.8125rem; font-weight: 600;">
      {{ $trip->service_type_badge }}
    </span>
  </div>

  <!-- Scheduled Pickup Date & Time -->
  <section style="background-color: #0C1445; color: #ffffff; border-radius: 0.75rem; padding: 1.25rem; margin-bottom: 1.25rem; box-shadow: 0 4px 12px rgba(12, 20, 69, 0.15);" aria-labelledby="pickup-time-heading">
    <div id="pickup-time-heading" style="font-size: 0.75rem; text-transform: uppercase; font-weight: 600; color: #C5A880; letter-spacing: 0.05em; margin-bottom: 0.375rem;">
      Scheduled Pickup Time
    </div>
    <div style="font-size: 1.25rem; font-weight: 700; line-height: 1.3;">
      {{ $trip->scheduled_pickup_time_formatted }}
    </div>
  </section>

  <!-- Vehicle Assignment -->
  <section style="border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1rem 1.25rem; margin-bottom: 1.25rem; background: #ffffff;" aria-label="Assigned Vehicle">
    <div style="display: flex; justify-content: space-between; align-items: center;">
      <div>
        <div style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #64748b;">Assigned Fleet Vehicle</div>
        <div style="font-size: 1.125rem; font-weight: 700; color: #1e293b;">{{ $trip->vehicle_name }}</div>
      </div>
      <div style="background-color: #fef3c7; border: 2px solid #f59e0b; color: #78350f; font-family: monospace; font-size: 1.125rem; font-weight: 800; padding: 0.375rem 0.75rem; border-radius: 0.375rem; letter-spacing: 0.05em;" aria-label="License plate: {{ $trip->vehicle_license_plate }}">
        {{ $trip->vehicle_license_plate }}
      </div>
    </div>
  </section>

  <!-- Journey Route Card -->
  <section style="border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1.25rem; margin-bottom: 1.25rem; background: #ffffff;" aria-labelledby="route-heading">
    <h2 id="route-heading" style="font-size: 0.875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; margin: 0 0 1rem 0;">
      Journey Route
    </h2>

    <!-- Pickup -->
    <div style="margin-bottom: 1rem;">
      <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
        <span style="width: 10px; height: 10px; border-radius: 9999px; background-color: #10b981; display: inline-block;" aria-hidden="true"></span>
        <span style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #059669;">Pickup Location</span>
      </div>
      <div style="font-size: 1.0625rem; font-weight: 600; color: #1e293b; line-height: 1.4; padding-left: 1rem;">
        {{ $trip->pickup_location_text }}
      </div>
      @if ($trip->pickup_navigation_url)
        <div style="padding-left: 1rem; margin-top: 0.375rem;">
          <a href="{{ $trip->pickup_navigation_url }}" target="_blank" rel="noreferrer noopener" style="display: inline-flex; align-items: center; gap: 0.375rem; font-size: 0.8125rem; font-weight: 600; color: #0284c7; text-decoration: none;">
            <span>Open in Google Maps</span>
            <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
          </a>
        </div>
      @endif
    </div>

    <!-- Intermediate Stops if any -->
    @if (!empty($trip->intermediate_stops))
      <div style="margin-bottom: 1rem; padding-left: 1rem; border-left: 2px dashed #cbd5e1; margin-left: 5px;">
        <div style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #64748b; margin-bottom: 0.25rem;">Intermediate Stops</div>
        @foreach ($trip->intermediate_stops as $stop)
          <div style="font-size: 0.9375rem; color: #334155; margin-bottom: 0.375rem;">
            • {{ $stop['location'] }}
            @if ($stop['notes'])
              <span style="font-size: 0.8125rem; color: #64748b;">({{ $stop['notes'] }})</span>
            @endif
          </div>
        @endforeach
      </div>
    @endif

    <!-- Destination -->
    @if ($trip->destination_text)
      <div>
        <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.25rem;">
          <span style="width: 10px; height: 10px; border-radius: 9999px; background-color: #ef4444; display: inline-block;" aria-hidden="true"></span>
          <span style="font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #dc2626;">Destination</span>
        </div>
        <div style="font-size: 1.0625rem; font-weight: 600; color: #1e293b; line-height: 1.4; padding-left: 1rem;">
          {{ $trip->destination_text }}
        </div>
        @if ($trip->destination_navigation_url)
          <div style="padding-left: 1rem; margin-top: 0.375rem;">
            <a href="{{ $trip->destination_navigation_url }}" target="_blank" rel="noreferrer noopener" style="display: inline-flex; align-items: center; gap: 0.375rem; font-size: 0.8125rem; font-weight: 600; color: #0284c7; text-decoration: none;">
              <span>Open in Google Maps</span>
              <svg style="width: 14px; height: 14px;" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
            </a>
          </div>
        @endif
      </div>
    @endif
  </section>

  <!-- Flight & Name Board Details (Airport Services) -->
  @if ($trip->flight_number || $trip->name_board_text || $trip->waiting_instructions)
    <section style="background-color: #f8fafc; border: 1px solid #cbd5e1; border-radius: 0.75rem; padding: 1.25rem; margin-bottom: 1.25rem;" aria-labelledby="arrival-heading">
      <h2 id="arrival-heading" style="font-size: 0.875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #1e293b; margin: 0 0 0.875rem 0;">
        Airport Arrival & Meet Details
      </h2>

      @if ($trip->flight_number)
        <div style="display: flex; gap: 1.5rem; margin-bottom: 0.75rem;">
          <div>
            <div style="font-size: 0.75rem; color: #64748b; font-weight: 600;">Flight Number</div>
            <div style="font-size: 1.125rem; font-weight: 700; color: #0C1445;">{{ $trip->flight_number }}</div>
          </div>
          @if ($trip->flight_arrival_time_formatted)
            <div>
              <div style="font-size: 0.75rem; color: #64748b; font-weight: 600;">Scheduled Flight Touchdown</div>
              <div style="font-size: 1.125rem; font-weight: 700; color: #0C1445;">{{ $trip->flight_arrival_time_formatted }}</div>
            </div>
          @endif
        </div>
      @endif

      @if ($trip->name_board_text)
        <div style="background-color: #ffffff; border: 2px solid #0C1445; border-radius: 0.5rem; padding: 0.875rem 1rem; margin-top: 0.75rem; text-align: center;">
          <div style="font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; color: #64748b; letter-spacing: 0.05em;">Arrival Placard / Name Board</div>
          <div style="font-size: 1.375rem; font-weight: 800; color: #0C1445; letter-spacing: 0.02em; margin-top: 0.25rem;">
            {{ $trip->name_board_text }}
          </div>
        </div>
      @endif

      @if ($trip->waiting_instructions)
        <div style="margin-top: 0.75rem; font-size: 0.875rem; color: #334155; line-height: 1.4;">
          <strong>Instructions:</strong> {{ $trip->waiting_instructions }}
        </div>
      @endif
    </section>
  @endif

  <!-- Passenger & Luggage Specs -->
  <section style="display: flex; gap: 1rem; margin-bottom: 1.25rem;">
    <div style="flex: 1; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 0.875rem 1rem; background: #ffffff; text-align: center;">
      <div style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #64748b;">Passengers</div>
      <div style="font-size: 1.25rem; font-weight: 700; color: #0f172a; margin-top: 0.25rem;">{{ $trip->passenger_count }} Adult(s)</div>
    </div>
    <div style="flex: 1; border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 0.875rem 1rem; background: #ffffff; text-align: center;">
      <div style="font-size: 0.75rem; font-weight: 600; text-transform: uppercase; color: #64748b;">Luggage</div>
      <div style="font-size: 1.25rem; font-weight: 700; color: #0f172a; margin-top: 0.25rem;">{{ $trip->luggage_count }} Piece(s)</div>
    </div>
  </section>

  <!-- Lead Traveler Contact (Strict Time-gating) -->
  <section style="border: 1px solid #e2e8f0; border-radius: 0.75rem; padding: 1.25rem; margin-bottom: 1.5rem; background: #ffffff;" aria-labelledby="traveler-heading">
    <h2 id="traveler-heading" style="font-size: 0.875rem; font-weight: 700; text-transform: uppercase; letter-spacing: 0.05em; color: #475569; margin: 0 0 0.75rem 0;">
      Traveler Contact
    </h2>
    <div style="font-size: 1.125rem; font-weight: 700; color: #0f172a; margin-bottom: 0.75rem;">
      {{ $trip->lead_traveler_first_name }}
    </div>

    @if ($trip->lead_traveler_phone_clean)
      <div style="display: flex; gap: 0.75rem; flex-wrap: wrap;">
        <a href="tel:{{ $trip->lead_traveler_phone_clean }}" class="btn-touch" style="flex: 1; background-color: #0C1445; color: #ffffff; text-decoration: none; padding: 0 1rem; font-size: 0.9375rem;">
          <svg style="width: 18px; height: 18px; margin-right: 0.5rem;" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z" /></svg>
          <span>Call Traveler</span>
        </a>

        @if ($trip->lead_traveler_whatsapp_url)
          <a href="{{ $trip->lead_traveler_whatsapp_url }}" target="_blank" rel="noreferrer noopener" class="btn-touch" style="flex: 1; background-color: #25D366; color: #ffffff; text-decoration: none; padding: 0 1rem; font-size: 0.9375rem;">
            <span>WhatsApp</span>
          </a>
        @endif
      </div>
    @elseif ($trip->current_status->value === 'completed')
      <div style="font-size: 0.8125rem; color: #64748b; background-color: #f8fafc; padding: 0.625rem; border-radius: 0.375rem;">
        Traveler direct contact archived upon journey completion.
      </div>
    @else
      <div style="font-size: 0.8125rem; color: #64748b; background-color: #f8fafc; padding: 0.625rem; border-radius: 0.375rem;">
        Traveler contact channels unlock 24 hours prior to scheduled pickup for privacy protection.
      </div>
    @endif
  </section>

  <!-- Active Status & Chauffeur Action Controls -->
  <section style="background-color: #ffffff; border: 2px solid #0C1445; border-radius: 0.75rem; padding: 1.25rem; margin-bottom: 1.5rem;" aria-labelledby="status-heading">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 1.25rem;">
      <h2 id="status-heading" style="font-size: 0.875rem; font-weight: 700; text-transform: uppercase; color: #64748b; margin: 0;">
        Current Status
      </h2>
      <span style="display: inline-flex; align-items: center; gap: 0.375rem; background-color: #0C1445; color: #ffffff; font-size: 0.875rem; font-weight: 700; padding: 0.375rem 0.75rem; border-radius: 9999px;">
        {{ $trip->current_status_label }}
      </span>
    </div>

    @if ($trip->issue_notes)
      <div style="background-color: #fffbeb; border-left: 4px solid #f59e0b; padding: 0.75rem 1rem; border-radius: 0.375rem; margin-bottom: 1.25rem; font-size: 0.875rem; color: #92400e;">
        <strong>Reported Issue:</strong> {{ $trip->issue_notes }}
        <div style="font-size: 0.75rem; color: #b45309; margin-top: 0.25rem;">
          Recorded {{ $trip->issue_reported_at?->diffForHumans() }}
        </div>
      </div>
    @endif

    <!-- Milestone Progression Action Button -->
    @if (!empty($trip->allowed_next_milestones))
      @php
        $nextAction = $trip->allowed_next_milestones[0];
        $buttonText = match($nextAction->value) {
            'acknowledged' => 'Confirm & Acknowledge Trip',
            'en_route' => 'Start Driving / En Route to Pickup',
            'arrived' => 'Arrived at Pickup Location',
            'started' => 'Passenger Onboard / Start Trip',
            'completed' => 'Trip Completed Successfully',
            default => 'Advance Status: ' . $nextAction->label(),
        };
        $buttonColor = $nextAction->value === 'completed' ? '#059669' : '#0C1445';
      @endphp

      <form method="POST" action="{{ route('driver.trip.status', ['token' => $rawToken]) }}" style="margin-bottom: 0.875rem;" onsubmit="appendClientTimestamp(this)">
        @csrf
        <input type="hidden" name="status" value="{{ $nextAction->value }}">
        <input type="hidden" name="client_timestamp" class="js-client-timestamp" value="">
        <button type="submit" class="btn-touch" style="width: 100%; background-color: {{ $buttonColor }}; color: #ffffff; border: none; font-size: 1.0625rem; padding: 0.875rem 1rem;">
          {{ $buttonText }}
        </button>
      </form>
    @elseif ($trip->current_status->value === 'completed')
      <div style="background-color: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 0.5rem; padding: 1rem; text-align: center; color: #065f46; font-weight: 600; margin-bottom: 0.875rem;">
        Journey Completed. Thank you for professional service!
      </div>
    @endif

    <!-- Secondary: Report Delay / Issue Button -->
    @if ($trip->current_status->value !== 'completed')
      <button type="button" class="btn-touch" style="width: 100%; background-color: #ffffff; color: #b45309; border: 1px solid #d97706; font-size: 0.9375rem; padding: 0.625rem 1rem;" onclick="document.getElementById('issue-dialog').showModal()">
        Report Delay / Issue
      </button>
    @endif
  </section>
</article>

<!-- Modern Native Modal Dialog for Reporting Issue (MWG Compliant) -->
<dialog id="issue-dialog" closedby="any" aria-labelledby="issue-dialog-title">
  <form method="POST" action="{{ route('driver.trip.issue', ['token' => $rawToken]) }}" onsubmit="appendClientTimestamp(this)">
    @csrf
    <input type="hidden" name="client_timestamp" class="js-client-timestamp" value="">

    <h2 id="issue-dialog-title" style="font-size: 1.125rem; font-weight: 700; color: #0C1445; margin: 0 0 0.5rem 0;">
      Report Delay or Operational Issue
    </h2>
    <p style="font-size: 0.875rem; color: #64748b; margin: 0 0 1rem 0; line-height: 1.4;">
      Enter road delay, traffic condition, or traveler meeting note. This is immediately logged for the fleet dispatch desk.
    </p>

    <div style="margin-bottom: 1.25rem;">
      <label for="issue-notes" style="display: block; font-size: 0.8125rem; font-weight: 600; color: #1e293b; margin-bottom: 0.375rem;">
        Description of Delay / Issue
      </label>
      <textarea id="issue-notes" name="notes" rows="3" required placeholder="e.g. Heavy congestion near expressway exit, estimated 15 min delay." style="width: 100%; border: 1px solid #cbd5e1; border-radius: 0.5rem; padding: 0.625rem; font-size: 0.9375rem; font-family: inherit; box-sizing: border-box;"></textarea>
    </div>

    <div style="display: flex; gap: 0.75rem; justify-content: flex-end;">
      <button type="button" class="btn-touch" style="background: #f1f5f9; color: #475569; border: none; padding: 0 1rem; font-size: 0.9375rem;" onclick="document.getElementById('issue-dialog').close()">
        Cancel
      </button>
      <button type="submit" class="btn-touch" style="background: #d97706; color: #ffffff; border: none; padding: 0 1.25rem; font-size: 0.9375rem;">
        Send to Dispatch
      </button>
    </div>
  </form>
</dialog>
@endsection

@section('scripts')
<script>
  // Record device timestamp before submission for cellular dead-zone synchronization
  function appendClientTimestamp(form) {
    const input = form.querySelector('.js-client-timestamp');
    if (input) {
      input.value = new Date().toISOString();
    }
  }

  // Modern Web Guidance: Light-dismiss fallback for browsers without native closedby support
  (function() {
    const dialog = document.getElementById('issue-dialog');
    if (dialog && !('closedBy' in HTMLDialogElement.prototype)) {
      dialog.addEventListener('click', function(event) {
        if (event.target !== dialog) return;
        const rect = dialog.getBoundingClientRect();
        const isDialogContent = (
          rect.top <= event.clientY &&
          event.clientY <= rect.top + rect.height &&
          rect.left <= event.clientX &&
          event.clientX <= rect.left + rect.width
        );
        if (!isDialogContent) {
          dialog.close();
        }
      });
    }
  })();
</script>
@endsection

