@extends('layouts.app')

@section('title', 'Booking Control & Status — '.$booking->reference.' — Spinellanka')
@section('meta_robots', 'noindex, nofollow')

@section('content')
<main id="main-content" class="booking-status-page">
  <div class="booking-container">

    <!-- Flash Announcements (polite live region for screen readers) -->
    <div aria-live="polite" aria-atomic="true">
      @if(session('status_success'))
        <div class="alert alert-success" role="alert" style="margin-bottom: 1.5rem; padding: 1rem 1.25rem; border-radius: 8px; background: #ecfdf5; border: 1px solid #a7f3d0; color: #065f46; display: flex; align-items: center; gap: 0.75rem;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><polyline points="22 4 12 14.01 9 11.01"></polyline></svg>
          <div>
            <strong>Success:</strong> {{ session('status_success') }}
          </div>
        </div>
      @endif

      @if(session('payment_pending'))
        <div class="alert alert-info" role="status" style="margin-bottom: 1.5rem; padding: 1rem 1.25rem; border-radius: 8px; background: #e0f2fe; border: 1px solid #7dd3fc; color: #0369a1; display: flex; align-items: center; gap: 0.75rem;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 14 14"></polyline></svg>
          <div>
            <strong>Payment Confirmation In Progress:</strong> {{ session('payment_pending') }}
          </div>
        </div>
      @endif

      @if(session('error') || $errors->any())
        <div class="alert alert-danger" role="alert" style="margin-bottom: 1.5rem; padding: 1rem 1.25rem; border-radius: 8px; background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; display: flex; align-items: flex-start; gap: 0.75rem;">
          <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true" style="margin-top: 2px;"><circle cx="12" cy="12" r="10"></circle><line x1="12" y1="8" x2="12" y2="12"></line><line x1="12" y1="16" x2="12.01" y2="16"></line></svg>
          <div>
            <strong>Attention Required:</strong>
            @if(session('error'))
              <p style="margin: 0.25rem 0 0;">{{ session('error') }}</p>
            @endif
            @if($errors->any())
              <ul style="margin: 0.25rem 0 0; padding-left: 1.25rem;">
                @foreach($errors->all() as $err)
                  <li>{{ $err }}</li>
                @endforeach
              </ul>
            @endif
          </div>
        </div>
      @endif
    </div>

    <!-- Status Header Card -->
    <header class="status-hero-card" aria-labelledby="status-title">
      <div class="status-badge-row">
        @if($booking->status === \App\Domain\Booking\Enums\BookingStatus::Confirmed)
          @if($booking->pending_revision)
            <span class="status-pill status-revision" style="background: #fef3c7; color: #92400e; border: 1px solid #fcd34d;">
              <span class="status-dot amber"></span> Confirmed &bull; Amendment Under Review
            </span>
          @else
            <span class="status-pill status-confirmed">
              <span class="status-dot green"></span> Confirmed by Dispatch
            </span>
          @endif
        @elseif($booking->status === \App\Domain\Booking\Enums\BookingStatus::ProposalSent)
          <span class="status-pill status-proposal" style="background: #e0f2fe; color: #0369a1; border: 1px solid #7dd3fc;">
            <span class="status-dot blue"></span> Official Proposal Ready for Review
          </span>
        @elseif($booking->status === \App\Domain\Booking\Enums\BookingStatus::AwaitingPayment)
          <span class="status-pill status-awaiting-payment" style="background: #fef3c7; color: #92400e; border: 1px solid #fcd34d;">
            <span class="status-dot amber"></span> Proposal Accepted &bull; Awaiting Payment
          </span>
        @elseif($booking->status === \App\Domain\Booking\Enums\BookingStatus::Declined)
          <span class="status-pill status-declined">
            <span class="status-dot red"></span> Request Declined
          </span>
        @elseif($booking->status === \App\Domain\Booking\Enums\BookingStatus::Completed)
          <span class="status-pill status-completed" style="background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0;">
            <span class="status-dot green"></span> Service Completed
          </span>
        @elseif($booking->status === \App\Domain\Booking\Enums\BookingStatus::Cancelled)
          <span class="status-pill status-cancelled">
            <span class="status-dot gray"></span> Cancelled
          </span>
        @elseif($booking->status === \App\Domain\Booking\Enums\BookingStatus::Expired)
          <span class="status-pill status-expired">
            <span class="status-dot gray"></span> Request Expired
          </span>
        @else
          <!-- Default for submitted / under_review: NEVER say confirmed -->
          <span class="status-pill status-pending">
            <span class="status-dot amber"></span> Request Received &bull; Under Review
          </span>
        @endif

        <span class="booking-ref-badge" aria-label="Booking reference number">
          Reference: <strong>{{ $booking->reference }}</strong>
        </span>
      </div>

      <h1 id="status-title">
        @if($booking->status === \App\Domain\Booking\Enums\BookingStatus::Confirmed)
          @if($booking->pending_revision)
            Travel Agreement Confirmed — Amendment Under Review
          @elseif($booking->service_type === \App\Domain\Booking\ServiceType::Rent)
            Your Self-Drive Vehicle Hire is Confirmed
          @elseif($booking->service_type === \App\Domain\Booking\ServiceType::Ride)
            Your Chauffeured Ride is Confirmed
          @elseif($booking->service_type === \App\Domain\Booking\ServiceType::AirportTransfer)
            Your Airport Transfer is Confirmed
          @else
            Your Airport Pickup is Confirmed
          @endif
        @elseif($booking->status === \App\Domain\Booking\Enums\BookingStatus::ProposalSent)
          Official Dispatch Proposal Ready
        @elseif($booking->status === \App\Domain\Booking\Enums\BookingStatus::AwaitingPayment)
          Proposal Accepted — Awaiting Payment Settlement
        @elseif($booking->status === \App\Domain\Booking\Enums\BookingStatus::Completed)
          Service Successfully Completed — Thank You
        @elseif($booking->status === \App\Domain\Booking\Enums\BookingStatus::Declined)
          Reservation Request Declined
        @else
          Request Received — Under Review
        @endif
      </h1>

      <p class="status-lead">
        @if($booking->status === \App\Domain\Booking\Enums\BookingStatus::Confirmed)
          @if($booking->pending_revision)
            Your original confirmed booking remains active and protected. Our dispatch team is reviewing your requested adjustments or has prepared a revision proposal below.
          @elseif($booking->service_type === \App\Domain\Booking\ServiceType::Rent)
            Our dispatch desk has confirmed vehicle preparation and allocated your reserved exact vehicle for handover.
          @elseif($booking->service_type === \App\Domain\Booking\ServiceType::Ride)
            Our dispatch desk has confirmed your route feasibility and allocated your dedicated chauffeur.
          @else
            Our dispatch team has verified your flight arrival schedule and assigned your dedicated driver.
          @endif
        @elseif($booking->status === \App\Domain\Booking\Enums\BookingStatus::ProposalSent)
          Our operations desk has verified route feasibility, turnaround buffers, and allocated fleet capacity. Please review the official proposal, options, and response terms below.
        @elseif($booking->status === \App\Domain\Booking\Enums\BookingStatus::AwaitingPayment)
          Thank you for accepting our proposal. Your vehicle hold is secured. Please finalize your advance settlement or security deposit below to complete confirmation.
        @elseif($booking->status === \App\Domain\Booking\Enums\BookingStatus::Completed)
          Your journey with Spinellanka has been successfully concluded. We hope you had a pleasant and comfortable journey.
        @elseif($booking->status === \App\Domain\Booking\Enums\BookingStatus::Declined)
          {{ $booking->decline_reason ?? 'Unfortunately, we are unable to accommodate this schedule.' }}
        @else
          Thank you for choosing Spinellanka. Your advance reservation request is currently being reviewed by our operations desk. We verify vehicle preparation and turnaround buffers before manual confirmation. No payment has been processed.
        @endif
      </p>

      <div class="private-link-notice" role="note">
        <svg class="lock-icon" aria-hidden="true" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
          <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
        </svg>
        <span>This private status page is secured with your personal access token. Bookmark this URL to manage your reservation at any time.</span>
      </div>
    </header>

    @if($booking->pending_revision && $booking->status === \App\Domain\Booking\Enums\BookingStatus::Confirmed)
      <!-- Pillar B Invariant Callout -->
      <div class="revision-guard-card" style="margin-bottom: 1.5rem; padding: 1rem 1.25rem; border-radius: 8px; background: #fffbeb; border: 1px solid #fde68a; color: #78350f;">
        <div style="display: flex; gap: 0.75rem; align-items: flex-start;">
          <span style="font-size: 1.25rem;">🛡️</span>
          <div>
            <strong style="font-size: 1rem;">Confirmed Travel Agreement Protected (Pillar B Guarantee)</strong>
            <p style="margin: 0.25rem 0 0; font-size: 0.875rem; line-height: 1.5;">
              While schedule adjustments or quotes are being considered, your existing confirmed vehicle reservation remains <strong>100% active, guaranteed, and authoritative</strong>. An amended proposal will only take effect if you explicitly accept it. If declined or expired, your original travel agreement continues unchanged.
            </p>
          </div>
        </div>
      </div>
    @endif

    @if($proposal)
      <!-- Official Proposal Section -->
      <section class="booking-card proposal-card" aria-labelledby="proposal-card-heading" style="border: 2px solid var(--color-brand, #0284c7); margin-bottom: 2rem;">
        <div class="proposal-banner-header" style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 0.75rem; padding-bottom: 1rem; border-bottom: 1px solid var(--color-border, #e2e8f0);">
          <div>
            <span class="version-tag" style="background: var(--color-brand, #0284c7); color: #ffffff; padding: 0.2rem 0.5rem; border-radius: 4px; font-size: 0.8rem; font-weight: 600; text-transform: uppercase;">
              Proposal v{{ $proposal->version }}
            </span>
            <span style="margin-left: 0.5rem; font-weight: 600; color: var(--color-text-primary, #0f172a);">{{ $proposal->reference }}</span>
          </div>

          <div class="proposal-status-info">
            @if($proposal->status === \App\Domain\Booking\Enums\ProposalStatus::Accepted)
              <span class="badge badge-success" style="background: #ecfdf5; color: #047857; padding: 0.35rem 0.75rem; border-radius: 6px; font-size: 0.85rem; font-weight: 600;">
                ✓ Accepted on {{ $proposal->respondedAt?->format('d M Y @ H:i') }}
              </span>
            @elseif($proposal->status === \App\Domain\Booking\Enums\ProposalStatus::Declined)
              <span class="badge badge-danger" style="background: #fef2f2; color: #b91c1c; padding: 0.35rem 0.75rem; border-radius: 6px; font-size: 0.85rem; font-weight: 600;">
                ✕ Declined on {{ $proposal->respondedAt?->format('d M Y @ H:i') }}
              </span>
            @elseif($proposal->isExpired)
              <span class="badge badge-expired" style="background: #f1f5f9; color: #475569; padding: 0.35rem 0.75rem; border-radius: 6px; font-size: 0.85rem; font-weight: 600;">
                ⏰ Expired on {{ $proposal->expiresAt->format('d M Y @ H:i') }}
              </span>
            @else
              <span class="badge badge-active" style="background: #eff6ff; color: #1d4ed8; padding: 0.35rem 0.75rem; border-radius: 6px; font-size: 0.85rem; font-weight: 600;">
                ⏱ Response Deadline: {{ $proposal->expiresAt->format('D, d M Y @ H:i') }}
              </span>
            @endif
          </div>
        </div>

        <h2 id="proposal-card-heading" class="card-heading" style="margin-top: 1rem;">Official Service &amp; Rate Proposal</h2>

        @if($proposal->staffNotes)
          <div class="staff-quote-box" style="margin: 1rem 0; padding: 1rem; background: #f8fafc; border-left: 4px solid var(--color-brand, #0284c7); border-radius: 4px;">
            <strong style="display: block; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.05em; color: #64748b; margin-bottom: 0.25rem;">Note from Dispatch Specialist:</strong>
            <p style="margin: 0; font-size: 0.95rem; color: #1e293b; font-style: italic;">&ldquo;{{ $proposal->staffNotes }}&rdquo;</p>
          </div>
        @endif

        @if(!empty($comparison))
          <!-- Side-by-Side Comparison -->
          <div class="comparison-section" style="margin: 1.5rem 0;">
            <h3 style="font-size: 0.95rem; font-weight: 600; color: #334155; margin-bottom: 0.5rem;">Comparison with Initial Request</h3>
            <div style="overflow-x: auto;">
              <table class="comparison-table" style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                <thead>
                  <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                    <th style="padding: 0.625rem; text-align: left; font-weight: 600; color: #475569;">Attribute</th>
                    <th style="padding: 0.625rem; text-align: left; font-weight: 600; color: #475569;">Initial Request</th>
                    <th style="padding: 0.625rem; text-align: left; font-weight: 600; color: #475569;">Proposed Offer</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($comparison as $item)
                    <tr style="border-bottom: 1px solid #f1f5f9; {{ $item['changed'] ? 'background: #f0fdf4;' : '' }}">
                      <td style="padding: 0.625rem; font-weight: 500; color: #1e293b;">
                        {{ $item['label'] }}
                        @if($item['changed'])
                          <span style="font-size: 0.75rem; background: #dcfce7; color: #15803d; padding: 0.1rem 0.35rem; border-radius: 4px; margin-left: 0.25rem;">Adjusted</span>
                        @endif
                      </td>
                      <td style="padding: 0.625rem; color: #64748b;">{{ $item['original'] }}</td>
                      <td style="padding: 0.625rem; font-weight: {{ $item['changed'] ? '600' : 'normal' }}; color: {{ $item['changed'] ? '#15803d' : '#1e293b' }};">
                        {{ $item['proposed'] }}
                      </td>
                    </tr>
                  @endforeach
                </tbody>
              </table>
            </div>
          </div>
        @endif

        <!-- Line Item Breakdown -->
        @if(!empty($proposal->items))
          <div class="proposal-items-section" style="margin: 1.5rem 0;">
            <h3 style="font-size: 0.95rem; font-weight: 600; color: #334155; margin-bottom: 0.5rem;">Itemized Pricing Details</h3>
            <div style="overflow-x: auto;">
              <table style="width: 100%; border-collapse: collapse; font-size: 0.9rem;">
                <thead>
                  <tr style="background: #f8fafc; border-bottom: 2px solid #e2e8f0;">
                    <th style="padding: 0.625rem; text-align: left; font-weight: 600; color: #475569;">Service Component</th>
                    <th style="padding: 0.625rem; text-align: right; font-weight: 600; color: #475569;">Quantity</th>
                    <th style="padding: 0.625rem; text-align: right; font-weight: 600; color: #475569;">Rate</th>
                    <th style="padding: 0.625rem; text-align: right; font-weight: 600; color: #475569;">Total</th>
                  </tr>
                </thead>
                <tbody>
                  @foreach($proposal->items as $it)
                    <tr style="border-bottom: 1px solid #f1f5f9;">
                      <td style="padding: 0.625rem; color: #1e293b;">
                        {{ $it['description'] }}
                        @if($it['is_deposit'])
                          <span style="font-size: 0.75rem; background: #fef3c7; color: #92400e; padding: 0.1rem 0.35rem; border-radius: 4px; margin-left: 0.25rem;">Refundable Deposit</span>
                        @endif
                      </td>
                      <td style="padding: 0.625rem; text-align: right; color: #64748b;">
                        {{ rtrim(rtrim((string)$it['quantity'], '0'), '.') }}
                      </td>
                      <td style="padding: 0.625rem; text-align: right; color: #64748b;">
                        {{ $proposal->currency }} {{ number_format($it['unit_price_minor_units'] / 100, 2) }}
                      </td>
                      <td style="padding: 0.625rem; text-align: right; font-weight: 600; color: #0f172a;">
                        {{ $proposal->currency }} {{ number_format($it['total_amount_minor_units'] / 100, 2) }}
                      </td>
                    </tr>
                  @endforeach
                </tbody>
                <tfoot>
                  <tr style="border-top: 2px solid #cbd5e1; background: #f8fafc;">
                    <td colspan="3" style="padding: 0.75rem; font-weight: 700; color: #0f172a; text-align: right;">Total Proposed Amount:</td>
                    <td style="padding: 0.75rem; font-weight: 700; color: #0f172a; text-align: right; font-size: 1.05rem;">
                      {{ $proposal->currency }} {{ number_format($proposal->totalAmountMinorUnits / 100, 2) }}
                    </td>
                  </tr>
                  @if($proposal->approximateLkrAmountMinorUnits)
                    <tr style="background: #f8fafc;">
                      <td colspan="4" style="padding: 0.5rem 0.75rem; text-align: right; font-size: 0.8rem; color: #64748b;">
                        Approximate Local Reference: LKR {{ number_format($proposal->approximateLkrAmountMinorUnits / 100, 0) }}
                      </td>
                    </tr>
                  @endif
                </tfoot>
              </table>
            </div>
          </div>
        @endif

        @if($proposal->isActionable)
          <!-- Guest Action Controls -->
          <div class="proposal-actions-container" style="margin-top: 2rem; padding-top: 1.25rem; border-top: 1px solid #e2e8f0; display: flex; gap: 0.75rem; flex-wrap: wrap; align-items: center;">
            <button type="button" class="btn btn-primary" onclick="openModal('accept-dialog')" style="background: #0284c7; color: #ffffff; padding: 0.75rem 1.5rem; border: none; border-radius: 6px; font-weight: 600; cursor: pointer; display: flex; align-items: center; gap: 0.5rem;">
              <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="20 6 9 17 4 12"></polyline></svg>
              Accept Proposal
            </button>

            <button type="button" class="btn btn-secondary" onclick="openModal('change-dialog')" style="background: #f1f5f9; color: #334155; padding: 0.75rem 1.25rem; border: 1px solid #cbd5e1; border-radius: 6px; font-weight: 600; cursor: pointer;">
              Request Adjustments
            </button>

            <button type="button" class="btn btn-danger-ghost" onclick="openModal('decline-dialog')" style="background: transparent; color: #dc2626; padding: 0.75rem 1.25rem; border: 1px solid #fecaca; border-radius: 6px; font-weight: 600; cursor: pointer; margin-left: auto;">
              Decline Proposal
            </button>
          </div>
        @endif

        @if($booking->status === \App\Domain\Booking\Enums\BookingStatus::AwaitingPayment)
          <div class="awaiting-payment-cta-box" style="margin-top: 1.5rem; padding: 1.25rem 1.5rem; background: #fffbeb; border: 2px solid #f59e0b; border-radius: 8px;">
            <div style="display: flex; align-items: flex-start; gap: 0.75rem; flex-wrap: wrap; justify-content: space-between;">
              <div>
                <h3 style="margin: 0 0 0.25rem; font-size: 1.1rem; color: #92400e;">Complete Online Settlement</h3>
                <p style="margin: 0; font-size: 0.9rem; color: #78350f;">
                  Your proposal acceptance has reserved your vehicle hold. Settle the requested advance online to finalize your reservation confirmation.
                </p>
              </div>
              <div style="text-align: right;">
                <span style="font-size: 0.8rem; color: #78350f; display: block;">Amount Due:</span>
                <strong style="font-size: 1.25rem; color: #0f172a;">
                  {{ $proposal->currency }} {{ number_format(($proposal->depositRequired ? $proposal->depositAmountMinorUnits : $proposal->totalAmountMinorUnits) / 100, 2) }}
                </strong>
              </div>
            </div>
            <div style="margin-top: 1rem; display: flex; align-items: center; gap: 1rem; flex-wrap: wrap;">
              <form method="POST" action="{{ route('booking.payment.checkout', ['reference' => $booking->reference, 'token' => $token]) }}">
                @csrf
                <button type="submit" class="btn btn-primary" style="background: #16a34a; color: #ffffff; padding: 0.75rem 1.5rem; border: none; border-radius: 6px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 0.5rem;">
                  <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect><line x1="1" y1="10" x2="23" y2="10"></line></svg>
                  Pay Online (Card / Visa / Mastercard)
                </button>
              </form>
              <span style="font-size: 0.8rem; color: #64748b;">
                Instant dispatch confirmation &bull; 256-bit TLS encryption
              </span>
            </div>
          </div>
        @endif
      </section>
    @endif

    <div class="booking-grid">
      <!-- Left Column: Details -->
      <div class="booking-main-col">
        @if($booking->service_type === \App\Domain\Booking\ServiceType::Rent && isset($rentDetail) && $rentDetail)
          <!-- Rent (Self-Drive) Details -->
          <section class="booking-card" aria-labelledby="rent-details-heading">
            <h2 id="rent-details-heading" class="card-heading">Self-Drive Rental &amp; Handover Details</h2>
            <dl class="details-grid">
              <div>
                <dt>Pickup Handover Date &amp; Time</dt>
                <dd>
                  <strong>{{ $rentDetail->pickup_datetime?->format('D, d M Y @ H:i') }}</strong>
                  <small class="tz-badge">Sri Lanka Time (Asia/Colombo / UTC+05:30)</small>
                </dd>
              </div>
              <div>
                <dt>Return Collection Date &amp; Time</dt>
                <dd>
                  <strong>{{ $rentDetail->return_datetime?->format('D, d M Y @ H:i') }}</strong>
                  <small class="tz-badge">Sri Lanka Time (Asia/Colombo / UTC+05:30)</small>
                </dd>
              </div>
              <div>
                <dt>Handover Location (Pickup)</dt>
                <dd>
                  <strong>{{ $rentDetail->pickup_location_text }}</strong>
                  <span class="safe-tag" style="display:inline-block; margin-top: 0.25rem;">{{ ucfirst($rentDetail->pickup_location_type) }} Handover</span>
                </dd>
              </div>
              <div>
                <dt>Collection Location (Return)</dt>
                <dd>
                  <strong>{{ $rentDetail->return_location_text }}</strong>
                  <span class="safe-tag" style="display:inline-block; margin-top: 0.25rem;">{{ ucfirst($rentDetail->return_location_type) }} Return</span>
                </dd>
              </div>
              <div>
                <dt>Rental Duration</dt>
                <dd>
                  <strong>{{ $rentDetail->duration_days }} Days{{ $rentDetail->extra_hours > 0 ? ' + '.$rentDetail->extra_hours.' Extra Hours' : '' }}</strong>
                </dd>
              </div>
              <div>
                <dt>Mileage Allowance</dt>
                <dd>{{ $rentDetail->included_distance_km }} km included cumulative (excess billed at return)</dd>
              </div>
              <div>
                <dt>Security Deposit</dt>
                <dd><strong>USD ${{ number_format($rentDetail->security_deposit_minor_units / 100, 2) }}</strong> (Refundable pre-auth)</dd>
              </div>
              <div>
                <dt>Insurance Coverage</dt>
                <dd>Comprehensive included (Max Excess: USD ${{ number_format($rentDetail->insurance_excess_minor_units / 100, 2) }})</dd>
              </div>
              <div>
                <dt>Lead Driver Eligibility</dt>
                <dd>Age: {{ $rentDetail->driver_age }} &bull; Licence: {{ $rentDetail->driver_licence_country }} ({{ $rentDetail->driver_licence_years }} yrs held) &bull; IDP: {{ $rentDetail->has_idp ? 'Yes' : 'No' }}</dd>
              </div>
              <div>
                <dt>Additional Driver</dt>
                <dd>{{ $rentDetail->has_additional_driver ? $rentDetail->additional_driver_name.' (Age: '.$rentDetail->additional_driver_age.')' : 'None registered' }}</dd>
              </div>
            </dl>

            <div class="alert alert-info" style="margin-top: 1rem; border-left: 4px solid var(--color-primary, #0f172a); background: rgba(15, 23, 42, 0.04); padding: 0.75rem 1rem; border-radius: 4px;">
              <strong>Mandatory In-Person Document Inspection at Handover:</strong>
              <p style="margin: 0.25rem 0 0; font-size: 0.875rem;">Under Sri Lankan law and insurance requirements, the lead driver must present original physical passport with valid visa, home country national driving licence, and International Driving Permit (IDP) or AAC endorsement before the vehicle can be released. Digital copies or photographs cannot be accepted.</p>
            </div>
          </section>
        @elseif($booking->service_type === \App\Domain\Booking\ServiceType::Ride && isset($rideDetail) && $rideDetail)
          <!-- Point-to-Point / Multi-Stop Chauffeured Ride Details -->
          <section class="booking-card" aria-labelledby="ride-details-heading">
            <h2 id="ride-details-heading" class="card-heading">Ride Itinerary &amp; Journey Details</h2>
            <dl class="details-grid">
              <div>
                <dt>Pickup Origin</dt>
                <dd><strong>{{ $rideDetail->origin_text }}</strong></dd>
              </div>
              <div>
                <dt>Pickup Date &amp; Time</dt>
                <dd>
                  <strong>{{ $rideDetail->pickup_time?->format('D, d M Y @ H:i') }}</strong>
                  <small class="tz-badge">Sri Lanka Time (Asia/Colombo / UTC+05:30)</small>
                </dd>
              </div>
              <div>
                <dt>Final Destination</dt>
                <dd><strong>{{ $rideDetail->destination_text }}</strong></dd>
              </div>
              <div>
                <dt>Travel Party</dt>
                <dd>{{ $rideDetail->passenger_count }} Passengers &bull; {{ $rideDetail->luggage_count }} Checked Luggage Bags</dd>
              </div>
              <div style="grid-column: 1 / -1;">
                <dt>Scheduled En-Route Stops ({{ $rideDetail->stops ? $rideDetail->stops->count() : 0 }})</dt>
                <dd>
                  @if($rideDetail->stops && $rideDetail->stops->isNotEmpty())
                    <div class="ride-stops-summary-list" style="margin: 0.5rem 0 0 0.25rem;">
                      @foreach($rideDetail->stops->sortBy('stop_order') as $st)
                        <div class="stop-summary-row" style="margin-bottom: 0.35rem;">
                          <span>{{ $loop->iteration }}. <strong>{{ $st->location_text }}</strong></span>
                          @if($st->planned_waiting_minutes > 0)
                            <span class="wait-tag" style="display: inline-block; margin-left: 0.5rem; font-size: 0.8rem; background: var(--color-surface-sunken, #f1f5f9); padding: 0.15rem 0.4rem; border-radius: 4px;">
                              ⏱ {{ $st->planned_waiting_minutes }} min planned waiting
                            </span>
                          @endif
                        </div>
                      @endforeach
                    </div>
                  @else
                    <span>Direct journey selected (no intermediate stops).</span>
                  @endif
                </dd>
              </div>
              <div style="grid-column: 1 / -1;">
                <dt>Return Journey Leg</dt>
                <dd>
                  @if($rideDetail->has_return)
                    <strong>Scheduled Return Pickup: {{ $rideDetail->return_pickup_time?->format('D, d M Y @ H:i') }}</strong>
                    <p style="margin: 0.25rem 0 0; font-size: 0.875rem; color: var(--color-text-secondary, #64748b);">
                      Route: {{ $rideDetail->return_origin_text ?: $rideDetail->destination_text }} &rarr; {{ $rideDetail->return_destination_text ?: $rideDetail->origin_text }} (Same exact vehicle and dedicated chauffeur).
                    </p>
                  @else
                    <span>One-way journey (no return leg scheduled).</span>
                  @endif
                </dd>
              </div>
            </dl>

            <!-- Phase 12 Presentation Boundary: Route Map Container with Zero Geolocation & Accessible Text Itinerary Fallback -->
            <div id="ride-route-map"
                 class="ride-route-map-boundary"
                 data-origin="{{ $rideDetail->origin_text }}"
                 data-destination="{{ $rideDetail->destination_text }}"
                 data-has-return="{{ $rideDetail->has_return ? '1' : '0' }}"
                 data-stops='@json($rideDetail->stops->map(fn($s) => ["order" => $s->stop_order, "location" => $s->location_text, "waiting" => $s->planned_waiting_minutes]))'
                 style="margin-top: 1.5rem; padding: 1rem; border: 1px solid var(--color-border, #e2e8f0); border-radius: 8px; background: var(--color-surface, #ffffff);"
                 aria-label="Route Itinerary Overview">
              <div class="map-placeholder-header" style="display: flex; align-items: center; justify-content: space-between; margin-bottom: 0.75rem;">
                <span style="font-weight: 600; font-size: 0.925rem; display: flex; align-items: center; gap: 0.5rem;">
                  <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"></path><circle cx="12" cy="10" r="3"></circle></svg>
                  Journey Route Overview
                </span>
                <span class="route-badge" style="font-size: 0.75rem; padding: 0.2rem 0.5rem; background: var(--color-brand-surface, #e0f2fe); color: var(--color-brand, #0284c7); border-radius: 4px; font-weight: 500;">
                  Chauffeured Itinerary
                </span>
              </div>
              <!-- Accessible Text Itinerary Fallback (survives without JS / map provider) -->
              <div class="route-text-itinerary" aria-label="Step-by-step route itinerary">
                <div class="itinerary-stop" style="display: flex; gap: 0.75rem; align-items: flex-start; margin-bottom: 0.5rem;">
                  <span style="font-weight: bold; color: var(--color-brand, #0284c7);">A.</span>
                  <div>
                    <strong>Origin:</strong> {{ $rideDetail->origin_text }}
                  </div>
                </div>
                @foreach($rideDetail->stops->sortBy('stop_order') as $st)
                  <div class="itinerary-stop" style="display: flex; gap: 0.75rem; align-items: flex-start; margin-bottom: 0.5rem; padding-left: 0.5rem;">
                    <span style="font-weight: bold; color: var(--color-text-secondary, #64748b);">{{ chr(65 + $loop->iteration) }}.</span>
                    <div>
                      <strong>En-Route Stop {{ $st->stop_order }}:</strong> {{ $st->location_text }}
                      @if($st->planned_waiting_minutes > 0)
                        <span style="font-size: 0.8rem; color: var(--color-text-muted, #94a3b8);">({{ $st->planned_waiting_minutes }} min waiting)</span>
                      @endif
                    </div>
                  </div>
                @endforeach
                <div class="itinerary-stop" style="display: flex; gap: 0.75rem; align-items: flex-start; margin-bottom: 0.5rem;">
                  <span style="font-weight: bold; color: var(--color-brand, #0284c7);">{{ chr(65 + 1 + $rideDetail->stops->count()) }}.</span>
                  <div>
                    <strong>Destination:</strong> {{ $rideDetail->destination_text }}
                  </div>
                </div>
                @if($rideDetail->has_return)
                  <div class="itinerary-stop" style="display: flex; gap: 0.75rem; align-items: flex-start; margin-top: 0.75rem; padding-top: 0.5rem; border-top: 1px dashed var(--color-border, #e2e8f0);">
                    <span style="font-weight: bold; color: var(--color-success, #16a34a);">&hookleftarrow;</span>
                    <div>
                      <strong>Return Leg:</strong> {{ $rideDetail->return_origin_text ?: $rideDetail->destination_text }} &rarr; {{ $rideDetail->return_destination_text ?: $rideDetail->origin_text }}
                      <span style="display: block; font-size: 0.8rem; color: var(--color-text-muted, #94a3b8);">Scheduled: {{ $rideDetail->return_pickup_time?->format('D, d M Y @ H:i') }}</span>
                    </div>
                  </div>
                @endif
              </div>
              <p style="margin: 0.75rem 0 0; font-size: 0.75rem; color: var(--color-text-muted, #94a3b8);">
                * Route preview is illustrative. Chauffeur selects the optimal expressway route based on real-time traffic and highway conditions. Live dispatch tracking is not active during the request review stage.
              </p>
            </div>
          </section>
        @elseif($booking->service_type === \App\Domain\Booking\ServiceType::AirportTransfer && isset($transferDetail) && $transferDetail)
          <!-- Flight Departure & Transfer Details -->
          <section class="booking-card" aria-labelledby="transfer-details-heading">
            <h2 id="transfer-details-heading" class="card-heading">Departure Flight &amp; Transfer Details</h2>
            <dl class="details-grid">
              <div>
                <dt>Departure Airport</dt>
                <dd>Bandaranaike International Airport (CMB), Katunayake</dd>
              </div>
              <div>
                <dt>Flight Number</dt>
                <dd><strong>{{ $transferDetail->flight_number }}</strong></dd>
              </div>
              <div>
                <dt>Scheduled Departure</dt>
                <dd>
                  <strong>{{ $transferDetail->departure_time?->format('D, d M Y @ H:i') }}</strong>
                  <small class="tz-badge">Sri Lanka Time (Asia/Colombo / UTC+05:30)</small>
                </dd>
              </div>
              <div>
                <dt>Pickup Origin</dt>
                <dd>
                  <strong>{{ $transferDetail->origin_text }}</strong>
                  @if($transferDetail->origin_notes)
                    <p class="landmark-note"><em>Notes: {{ $transferDetail->origin_notes }}</em></p>
                  @endif
                </dd>
              </div>
              <div>
                <dt>Pickup Timing</dt>
                <dd>
                  <strong>{{ $transferDetail->chosen_pickup_time?->format('D, d M Y @ H:i') }}</strong>
                </dd>
              </div>
              <div>
                <dt>Travel Party</dt>
                <dd>{{ $transferDetail->passenger_count }} Passengers &bull; {{ $transferDetail->luggage_count }} Checked Luggage Bags</dd>
              </div>
            </dl>
          </section>
        @else
          <!-- Flight & Arrival Details -->
          <section class="booking-card" aria-labelledby="flight-details-heading">
            <h2 id="flight-details-heading" class="card-heading">Flight &amp; Arrival Details</h2>
            <dl class="details-grid">
              <div>
                <dt>Airport</dt>
                <dd>Bandaranaike International Airport (CMB), Katunayake</dd>
              </div>
              <div>
                <dt>Flight Number</dt>
                <dd><strong>{{ $detail?->flight_number }}</strong></dd>
              </div>
              <div>
                <dt>Scheduled Arrival</dt>
                <dd>
                  <strong>{{ $detail?->arrival_time?->format('D, d M Y @ H:i') }}</strong>
                  <small class="tz-badge">Sri Lanka Time (Asia/Colombo / UTC+05:30)</small>
                </dd>
              </div>
              <div>
                <dt>Drop-off Destination</dt>
                <dd>
                  <strong>{{ $detail?->destination_text }}</strong>
                  @if($detail?->destination_notes)
                    <p class="landmark-note"><em>Notes: {{ $detail->destination_notes }}</em></p>
                  @endif
                </dd>
              </div>
              <div>
                <dt>Travel Party</dt>
                <dd>{{ $detail?->passenger_count }} Passengers &bull; {{ $detail?->luggage_count }} Checked Luggage Bags</dd>
              </div>
              <div>
                <dt>Meet &amp; Greet</dt>
                <dd>
                  {{ $detail?->meet_and_greet ? 'Included with Welcome Board' : 'Standard Pickup' }}
                  @if($detail?->name_board_text)
                    <span class="nameboard-tag">Name Board: &ldquo;{{ $detail->name_board_text }}&rdquo;</span>
                  @endif
                </dd>
              </div>
            </dl>
          </section>
        @endif

        <!-- Vehicle Allocation Card -->
        @if($booking->vehicle)
          <section class="booking-card" aria-labelledby="vehicle-details-heading">
            <h2 id="vehicle-details-heading" class="card-heading">Reserved Exact Vehicle</h2>
            <div class="vehicle-summary-row">
              <div class="vehicle-meta">
                <h3>{{ $booking->vehicle->make }} {{ $booking->vehicle->model }} ({{ $booking->vehicle->year }})</h3>
                <p class="vehicle-specs">
                  Type: {{ $booking->vehicle->vehicle_type->getLabel() }} &bull; Plate: <strong>{{ $booking->vehicle->license_plate }}</strong>
                </p>
                <ul class="vehicle-perks">
                  @if($booking->service_type === \App\Domain\Booking\ServiceType::Rent)
                    <li>Self-drive vehicle hire (no driver assigned)</li>
                    <li>{{ $booking->vehicle->has_ac ? 'Air conditioned' : 'Non-AC' }}</li>
                    <li>{{ $booking->rentDetail ? ($booking->rentDetail->duration_days > 0 ? (int) round($booking->rentDetail->included_distance_km / $booking->rentDetail->duration_days) : 100) : 100 }} km/day cumulative mileage included</li>
                    <li>USD ${{ number_format(($booking->rentDetail ? $booking->rentDetail->security_deposit_minor_units : 50000) / 100, 2) }} refundable security deposit pre-authorization</li>
                  @else
                    <li>Chauffeured English-speaking driver included</li>
                    <li>{{ $booking->vehicle->has_ac ? 'Air conditioned' : 'Non-AC' }}</li>
                    @if($booking->service_type === \App\Domain\Booking\ServiceType::Ride)
                      <li>Southern / Central expressway tolls included in base rate</li>
                      <li>45-minute courtesy waiting time at origin included</li>
                    @elseif($booking->service_type === \App\Domain\Booking\ServiceType::AirportTransfer)
                      <li>Katunayake Expressway tolls and airport departure drop-off fees included</li>
                    @else
                      <li>Complimentary 90-minute customs and baggage waiting time</li>
                    @endif
                  @endif
                </ul>
              </div>
            </div>
          </section>
        @endif

        @if(($booking->status === \App\Domain\Booking\Enums\BookingStatus::Confirmed || $booking->status === \App\Domain\Booking\Enums\BookingStatus::Completed) && isset($assignedDriver) && $assignedDriver)
          <!-- Dedicated Chauffeur Allocation Card (Pillar C Transparency) -->
          <section class="booking-card" aria-labelledby="driver-details-heading" style="border: 2px solid #0C1445; background: #ffffff;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 0.75rem; margin-bottom: 1rem;">
              <h2 id="driver-details-heading" class="card-heading" style="margin: 0; color: #0C1445;">
                Dedicated Professional Chauffeur
              </h2>
              @if(isset($currentDriverAssignment) && $currentDriverAssignment)
                <span class="badge" style="background: #0C1445; color: #ffffff; padding: 0.35rem 0.75rem; border-radius: 9999px; font-size: 0.8125rem; font-weight: 700;">
                  Live Status: {{ $currentDriverAssignment->status->label() }}
                </span>
              @endif
            </div>

            <div style="display: flex; gap: 1.25rem; align-items: center; flex-wrap: wrap;">
              <div style="width: 72px; height: 72px; border-radius: 9999px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; font-size: 1.75rem; font-weight: 800; color: #0C1445; border: 2px solid #C5A880; overflow: hidden; flex-shrink: 0;">
                @if($assignedDriver->portrait_path)
                  <img src="{{ Storage::url($assignedDriver->portrait_path) }}" alt="{{ $assignedDriver->public_name }}" style="width: 100%; height: 100%; object-fit: cover;">
                @else
                  <span>{{ substr($assignedDriver->public_name, 0, 1) }}</span>
                @endif
              </div>

              <div style="flex: 1; min-width: 200px;">
                <h3 style="margin: 0 0 0.25rem 0; font-size: 1.15rem; color: #0f172a; font-weight: 700;">
                  {{ $assignedDriver->public_name }}
                </h3>
                <p style="margin: 0 0 0.5rem 0; font-size: 0.875rem; color: #64748b;">
                  Verified Sri Lanka Tourist Board Registered Chauffeur Guide
                </p>

                @if(!empty($assignedDriver->public_trust_facts))
                  <div style="display: flex; gap: 0.5rem; flex-wrap: wrap; margin-bottom: 0.5rem;">
                    @foreach($assignedDriver->public_trust_facts as $fact)
                      <span style="font-size: 0.75rem; background: #f8fafc; border: 1px solid #e2e8f0; color: #334155; padding: 0.15rem 0.5rem; border-radius: 4px;">
                        ✓ {{ $fact }}
                      </span>
                    @endforeach
                  </div>
                @endif

                @if($assignedDriver->whatsapp && $booking->status === \App\Domain\Booking\Enums\BookingStatus::Confirmed)
                  <div style="margin-top: 0.75rem;">
                    <a href="https://wa.me/{{ preg_replace('/[^0-9]/', '', $assignedDriver->whatsapp) }}" target="_blank" rel="noreferrer noopener" class="btn btn-sm" style="display: inline-flex; align-items: center; gap: 0.375rem; background: #25D366; color: #ffffff; text-decoration: none; padding: 0.4rem 0.875rem; border-radius: 6px; font-weight: 600; font-size: 0.85rem;">
                      <span>Direct WhatsApp with Chauffeur</span>
                      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"></path><polyline points="15 3 21 3 21 9"></polyline><line x1="10" y1="14" x2="21" y2="3"></line></svg>
                    </a>
                  </div>
                @endif
              </div>
            </div>
          </section>
        @endif

        <!-- Contacts Card -->
        <section class="booking-card" aria-labelledby="contacts-heading">
          <h2 id="contacts-heading" class="card-heading">Contact Information</h2>
          <div class="contacts-grid">
            @if($booker)
              <div class="contact-box">
                <h4>Booker (Primary Contact)</h4>
                <p><strong>{{ $booker->fullName() }}</strong></p>
                <p>WhatsApp / Phone: {{ strlen($booker->phone) > 6 ? substr($booker->phone, 0, 4) . ' ••••• ' . substr($booker->phone, -3) : $booker->phone }}</p>
                @if($booker->email)
                  <p>Email: {{ str_contains($booker->email, '@') ? substr($booker->email, 0, 1) . '•••••@' . explode('@', $booker->email)[1] : $booker->email }}</p>
                @endif
              </div>
            @endif

            @if($leadTraveler && $leadTraveler->id !== $booker?->id)
              <div class="contact-box">
                <h4>Lead Traveler at CMB Airport</h4>
                <p><strong>{{ $leadTraveler->fullName() }}</strong></p>
                <p>Phone: {{ strlen($leadTraveler->phone) > 6 ? substr($leadTraveler->phone, 0, 4) . ' ••••• ' . substr($leadTraveler->phone, -3) : $leadTraveler->phone }}</p>
              </div>
            @endif
          </div>
        </section>
      </div>

      <!-- Right Column: Estimate Snapshot, Guest Controls & Dispatch Process -->
      <aside class="booking-side-col">

        <!-- Guest Control Actions Card -->
        @if(!$booking->status->isTerminal())
          <div class="booking-card guest-actions-card" style="border: 1px solid var(--color-border, #e2e8f0); background: #ffffff; padding: 1.25rem; border-radius: 8px; margin-bottom: 1.5rem;">
            <h2 class="card-heading" style="font-size: 1rem; margin-bottom: 0.75rem;">Manage Your Reservation</h2>
            <div style="display: flex; flex-direction: column; gap: 0.625rem;">
              <button type="button" class="btn btn-outline" onclick="openModal('change-dialog')" style="width: 100%; padding: 0.625rem 1rem; border: 1px solid #cbd5e1; border-radius: 6px; background: #ffffff; color: #1e293b; font-weight: 600; cursor: pointer; text-align: left; display: flex; align-items: center; justify-content: space-between;">
                <span>Request Itinerary Changes</span>
                <span>&rarr;</span>
              </button>

              @if($booking->service_type === \App\Domain\Booking\ServiceType::Rent && $booking->status === \App\Domain\Booking\Enums\BookingStatus::Confirmed)
                <button type="button" class="btn btn-outline" onclick="openModal('extension-dialog')" style="width: 100%; padding: 0.625rem 1rem; border: 1px solid #cbd5e1; border-radius: 6px; background: #ffffff; color: #1e293b; font-weight: 600; cursor: pointer; text-align: left; display: flex; align-items: center; justify-content: space-between;">
                  <span>Request Hire Extension</span>
                  <span>+ Days</span>
                </button>
              @endif

              <button type="button" class="btn btn-outline-danger" onclick="openModal('cancel-dialog')" style="width: 100%; padding: 0.625rem 1rem; border: 1px solid #fecaca; border-radius: 6px; background: #ffffff; color: #dc2626; font-weight: 600; cursor: pointer; text-align: left; display: flex; align-items: center; justify-content: space-between;">
                <span>Cancel Reservation</span>
                <span>✕</span>
              </button>
            </div>
          </div>
        @endif

        <!-- Payments & Documents Card -->
        @if(($booking->paymentTransactions && $booking->paymentTransactions->isNotEmpty()) || ($booking->financialDocuments && $booking->financialDocuments->isNotEmpty()))
          <div class="booking-card payments-ledger-card" aria-labelledby="payments-ledger-heading">
            <h2 id="payments-ledger-heading" class="card-heading">Payments &amp; Official Documents</h2>

            <div style="display: flex; justify-content: space-between; align-items: center; padding-bottom: 0.75rem; border-bottom: 1px solid #e2e8f0; margin-bottom: 0.75rem;">
              <span style="font-size: 0.875rem; color: #64748b;">Total Settled:</span>
              <strong style="font-size: 1.05rem; color: #16a34a;">
                {{ $booking->currency ?: 'USD' }} {{ number_format($booking->totalSettledAmount() / 100, 2) }}
              </strong>
            </div>

            @if($booking->paymentTransactions && $booking->paymentTransactions->isNotEmpty())
              <h3 style="font-size: 0.8rem; text-transform: uppercase; color: #94a3b8; letter-spacing: 0.5px; margin: 0 0 0.5rem;">Transaction History</h3>
              <ul style="list-style: none; padding: 0; margin: 0 0 1rem;">
                @foreach($booking->paymentTransactions as $tx)
                  <li style="display: flex; justify-content: space-between; align-items: center; padding: 0.4rem 0; font-size: 0.875rem; border-bottom: 1px dashed #f1f5f9;">
                    <div>
                      <span style="font-weight: 600;">{{ $tx->payment_method->label() }}</span>
                      <small style="color: #64748b; display: block;">{{ $tx->created_at->format('d M H:i') }} &bull; {{ $tx->status->label() }}</small>
                    </div>
                    <span style="font-weight: 600; {{ $tx->amount_minor_units < 0 ? 'color: #dc2626;' : 'color: #0f172a;' }}">
                      {{ $tx->currency }} {{ number_format($tx->amount_minor_units / 100, 2) }}
                    </span>
                  </li>
                @endforeach
              </ul>
            @endif

            @if($booking->financialDocuments && $booking->financialDocuments->isNotEmpty())
              <h3 style="font-size: 0.8rem; text-transform: uppercase; color: #94a3b8; letter-spacing: 0.5px; margin: 0 0 0.5rem;">Official Issued Documents</h3>
              <ul style="list-style: none; padding: 0; margin: 0;">
                @foreach($booking->financialDocuments as $doc)
                  <li style="display: flex; justify-content: space-between; align-items: center; padding: 0.4rem 0; font-size: 0.875rem;">
                    <div>
                      <span style="font-weight: 600;">{{ $doc->document_type->label() }}</span>
                      <small style="color: #64748b; display: block;">{{ $doc->document_number }}</small>
                    </div>
                    <a href="{{ route('booking.documents.download', ['reference' => $booking->reference, 'token' => $token, 'documentNumber' => $doc->document_number]) }}" target="_blank" rel="noopener noreferrer" style="color: #0284c7; font-weight: 600; text-decoration: underline; font-size: 0.85rem;">
                      View / Print
                    </a>
                  </li>
                @endforeach
              </ul>
            @endif
          </div>
        @endif

        <!-- Pricing Estimate Card -->
        <div class="booking-card pricing-card" aria-labelledby="estimate-heading">
          <h2 id="estimate-heading" class="card-heading">Estimate Breakdown</h2>

          @if($booking->estimate && $booking->estimate->items->isNotEmpty())
            <ul class="breakdown-list">
              @foreach($booking->estimate->items as $item)
                <li>
                  <span>{{ $item->description }}</span>
                  <span>${{ number_format($item->total_amount_minor_units / 100, 2) }}</span>
                </li>
              @endforeach
            </ul>
          @endif

          <div class="total-row">
            <span>Total Estimated Fee:</span>
            @if($booking->total_amount_minor_units !== null)
              <strong>USD ${{ number_format($booking->total_amount_minor_units / 100, 2) }}</strong>
            @else
              <strong>Custom Quote</strong>
            @endif
          </div>

          @if($booking->approximate_lkr_amount_minor_units)
            <div class="lkr-basis-box">
              <span class="lkr-label">Approximate LKR Basis:</span>
              <span class="lkr-val">LKR {{ number_format($booking->approximate_lkr_amount_minor_units / 100, 0) }}*</span>
              <small class="lkr-disclaimer">*Indicative rate for local reference. Final settlement amount is in USD.</small>
            </div>
          @endif

          <div class="payment-policy-note">
            <small><strong>Payment timing:</strong> No advance payment is charged today. Payment is arranged upon manual confirmation or vehicle dispatch.</small>
          </div>
        </div>

        <!-- Next Steps Card -->
        <div class="booking-card next-steps-card" aria-labelledby="dispatch-steps-heading">
          <h2 id="dispatch-steps-heading" class="card-heading">What Happens Next?</h2>
          @if($booking->service_type === \App\Domain\Booking\ServiceType::Rent)
            <ol class="dispatch-steps-list">
              <li>
                <strong>Operations Logistics Review:</strong>
                <p>Our team verifies vehicle maintenance, cleaning, turnaround buffers, and handover readiness.</p>
              </li>
              <li>
                <strong>Direct WhatsApp Coordination:</strong>
                <p>We contact your international WhatsApp number to confirm exact handover timing and location instructions.</p>
              </li>
              <li>
                <strong>Vehicle Handover &amp; Document Inspection:</strong>
                <p>In-person verification of physical original passport, home licence, and IDP prior to vehicle release.</p>
              </li>
            </ol>
          @elseif($booking->service_type === \App\Domain\Booking\ServiceType::Ride)
            <ol class="dispatch-steps-list">
              <li>
                <strong>Itinerary &amp; Route Review:</strong>
                <p>Our operations desk verifies driver schedule, planned stop feasibility, and expressway routing.</p>
              </li>
              <li>
                <strong>Direct WhatsApp Confirmation:</strong>
                <p>We message your primary WhatsApp number with confirmed quote details and chauffeur allocation.</p>
              </li>
              <li>
                <strong>Doorstep Chauffeur Arrival:</strong>
                <p>Your dedicated chauffeur arrives at the pickup address with a 45-minute courtesy waiting window.</p>
              </li>
            </ol>
          @else
            <ol class="dispatch-steps-list">
              <li>
                <strong>Flight Dispatch Review:</strong>
                <p>Our team verifies driver availability, airport gate logistics, and flight schedule.</p>
              </li>
              <li>
                <strong>Direct WhatsApp Outreach:</strong>
                <p>We message your provided international number to confirm arrival details and driver contact.</p>
              </li>
              <li>
                <strong>Terminal Meet &amp; Greet:</strong>
                <p>Your driver waits inside the arrivals hall holding your name board.</p>
              </li>
            </ol>
          @endif

          <div class="support-action-box" style="margin-top: 1rem; padding-top: 1rem; border-top: 1px solid #e2e8f0;">
            <p style="font-size: 0.85rem; color: #64748b; margin-bottom: 0.5rem;">Need immediate assistance from dispatch?</p>
            <a href="{{ route('contact') }}" class="support-btn" style="display: inline-block; width: 100%; text-align: center; padding: 0.5rem; background: #f1f5f9; color: #1e293b; border-radius: 6px; font-weight: 600; text-decoration: none; border: 1px solid #cbd5e1;">Contact Operations Desk</a>
          </div>
        </div>
      </aside>
    </div>
  </div>

  <!-- Accessible Native Dialog Modals -->
  @if($proposal && $proposal->isActionable)
    <!-- Accept Proposal Modal -->
    <dialog id="accept-dialog" class="accessible-dialog" aria-labelledby="accept-dialog-heading">
      <div style="margin-bottom: 1.25rem;">
        <h2 id="accept-dialog-heading" style="font-size: 1.25rem; font-weight: 700; color: #0f172a; margin-bottom: 0.25rem;">Accept Official Proposal</h2>
        <p style="font-size: 0.875rem; color: #64748b; margin: 0;">Proposal Reference: <strong>{{ $proposal->reference }}</strong></p>
      </div>

      <form method="POST" action="{{ route('booking.status.accept', ['reference' => $booking->reference, 'token' => $token]) }}">
        @csrf

        <fieldset style="border: none; padding: 0; margin: 0 0 1.25rem 0;">
          <legend style="font-size: 0.95rem; font-weight: 600; color: #1e293b; margin-bottom: 0.5rem;">Select Payment Option:</legend>

          <div style="display: flex; flex-direction: column; gap: 0.5rem;">
            @foreach($proposal->allowedPaymentOptions as $opt)
              <label style="display: flex; align-items: flex-start; gap: 0.75rem; padding: 0.75rem; border: 1px solid #cbd5e1; border-radius: 8px; cursor: pointer; background: #f8fafc;">
                <input type="radio" name="payment_option" value="{{ $opt->value }}" {{ $loop->first ? 'checked' : '' }} style="margin-top: 0.2rem;">
                <div>
                  <strong style="color: #0f172a; display: block; font-size: 0.9rem;">
                    @if($opt === \App\Domain\Booking\Enums\PaymentOption::Full)
                      Pay in Full ({{ $proposal->currency }} {{ number_format($proposal->totalAmountMinorUnits / 100, 2) }})
                    @elseif($opt === \App\Domain\Booking\Enums\PaymentOption::Deposit)
                      Pay Security Deposit Now ({{ $proposal->currency }} {{ number_format($proposal->depositAmountMinorUnits / 100, 2) }})
                    @else
                      Pay on Arrival (Cash to Driver / Local Handover)
                    @endif
                  </strong>
                  <span style="font-size: 0.8rem; color: #64748b;">
                    @if($opt === \App\Domain\Booking\Enums\PaymentOption::Full)
                      Settle entire fee online now. No remaining balance upon arrival.
                    @elseif($opt === \App\Domain\Booking\Enums\PaymentOption::Deposit)
                      Refundable authorization to lock vehicle. Balance settled upon arrival.
                    @else
                      No online charge today. Settle with chauffeur at destination.
                    @endif
                  </span>
                </div>
              </label>
            @endforeach
          </div>
        </fieldset>

        <div style="margin-bottom: 1.25rem;">
          <label for="accept-notes" style="display: block; font-size: 0.875rem; font-weight: 600; color: #1e293b; margin-bottom: 0.35rem;">Additional Notes (Optional):</label>
          <textarea id="accept-notes" name="notes" rows="2" placeholder="Any special requests or arrival instructions..." style="width: 100%; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.875rem;"></textarea>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
          <button type="button" onclick="closeModal('accept-dialog')" style="padding: 0.5rem 1rem; border: 1px solid #cbd5e1; background: #ffffff; color: #475569; border-radius: 6px; font-weight: 600; cursor: pointer;">Dismiss</button>
          <button type="submit" style="padding: 0.5rem 1.25rem; border: none; background: #0284c7; color: #ffffff; border-radius: 6px; font-weight: 600; cursor: pointer;">Confirm Acceptance</button>
        </div>
      </form>
    </dialog>

    <!-- Decline Proposal Modal -->
    <dialog id="decline-dialog" class="accessible-dialog" aria-labelledby="decline-dialog-heading">
      <div style="margin-bottom: 1.25rem;">
        <h2 id="decline-dialog-heading" style="font-size: 1.25rem; font-weight: 700; color: #b91c1c; margin-bottom: 0.25rem;">Decline Official Proposal</h2>
        <p style="font-size: 0.875rem; color: #64748b; margin: 0;">Proposal Reference: <strong>{{ $proposal->reference }}</strong></p>
      </div>

      <form method="POST" action="{{ route('booking.status.decline', ['reference' => $booking->reference, 'token' => $token]) }}">
        @csrf

        @if($booking->status === \App\Domain\Booking\Enums\BookingStatus::Confirmed)
          <div style="margin-bottom: 1rem; padding: 0.75rem; background: #ecfdf5; border: 1px solid #a7f3d0; border-radius: 6px; color: #065f46; font-size: 0.85rem;">
            <strong>Pillar B Protection:</strong> Declining this revision proposal will <em>not</em> cancel your confirmed booking. Your existing confirmed travel agreement remains active.
          </div>
        @else
          <div style="margin-bottom: 1rem; padding: 0.75rem; background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; color: #991b1b; font-size: 0.85rem;">
            <strong>Note:</strong> Declining this proposal will close your reservation request.
          </div>
        @endif

        <div style="margin-bottom: 1.25rem;">
          <label for="decline-reason" style="display: block; font-size: 0.875rem; font-weight: 600; color: #1e293b; margin-bottom: 0.35rem;">Reason for Declining (Optional):</label>
          <textarea id="decline-reason" name="reason" rows="3" placeholder="Tell us why this quote or vehicle does not suit your needs..." style="width: 100%; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.875rem;"></textarea>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
          <button type="button" onclick="closeModal('decline-dialog')" style="padding: 0.5rem 1rem; border: 1px solid #cbd5e1; background: #ffffff; color: #475569; border-radius: 6px; font-weight: 600; cursor: pointer;">Keep Proposal</button>
          <button type="submit" style="padding: 0.5rem 1.25rem; border: none; background: #dc2626; color: #ffffff; border-radius: 6px; font-weight: 600; cursor: pointer;">Decline Proposal</button>
        </div>
      </form>
    </dialog>
  @endif

  <!-- Change Request Modal -->
  <dialog id="change-dialog" class="accessible-dialog" aria-labelledby="change-dialog-heading">
    <div style="margin-bottom: 1.25rem;">
      <h2 id="change-dialog-heading" style="font-size: 1.25rem; font-weight: 700; color: #0f172a; margin-bottom: 0.25rem;">Request Itinerary or Service Adjustments</h2>
      <p style="font-size: 0.875rem; color: #64748b; margin: 0;">Our dispatch desk will review and dispatch an updated quote/proposal.</p>
    </div>

    <form method="POST" action="{{ route('booking.status.change', ['reference' => $booking->reference, 'token' => $token]) }}">
      @csrf

      <div style="margin-bottom: 1.25rem;">
        <label for="change-notes" style="display: block; font-size: 0.875rem; font-weight: 600; color: #1e293b; margin-bottom: 0.35rem;">Requested Adjustments:</label>
        <textarea id="change-notes" name="notes" rows="4" required minlength="5" placeholder="Specify your requested changes (e.g., flight delay, new pickup time, additional passenger, changed dropoff location, etc.)..." style="width: 100%; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.875rem;"></textarea>
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
        <button type="button" onclick="closeModal('change-dialog')" style="padding: 0.5rem 1rem; border: 1px solid #cbd5e1; background: #ffffff; color: #475569; border-radius: 6px; font-weight: 600; cursor: pointer;">Cancel</button>
        <button type="submit" style="padding: 0.5rem 1.25rem; border: none; background: #0284c7; color: #ffffff; border-radius: 6px; font-weight: 600; cursor: pointer;">Submit Request</button>
      </div>
    </form>
  </dialog>

  <!-- Cancel Reservation Modal -->
  <dialog id="cancel-dialog" class="accessible-dialog" aria-labelledby="cancel-dialog-heading">
    <div style="margin-bottom: 1.25rem;">
      <h2 id="cancel-dialog-heading" style="font-size: 1.25rem; font-weight: 700; color: #dc2626; margin-bottom: 0.25rem;">Cancel Booking Reservation</h2>
      <p style="font-size: 0.875rem; color: #64748b; margin: 0;">Reference: <strong>{{ $booking->reference }}</strong></p>
    </div>

    <form method="POST" action="{{ route('booking.status.cancel', ['reference' => $booking->reference, 'token' => $token]) }}">
      @csrf

      <div style="margin-bottom: 1rem; padding: 0.75rem; background: #fef2f2; border: 1px solid #fecaca; border-radius: 6px; color: #991b1b; font-size: 0.85rem;">
        <strong>Warning:</strong> Cancelling releases your reserved vehicle and driver allocation immediately.
      </div>

      <div style="margin-bottom: 1.25rem;">
        <label for="cancel-reason" style="display: block; font-size: 0.875rem; font-weight: 600; color: #1e293b; margin-bottom: 0.35rem;">Reason for Cancellation:</label>
        <textarea id="cancel-reason" name="reason" rows="3" required minlength="5" placeholder="Please let us know why you need to cancel this reservation..." style="width: 100%; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.875rem;"></textarea>
      </div>

      <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
        <button type="button" onclick="closeModal('cancel-dialog')" style="padding: 0.5rem 1rem; border: 1px solid #cbd5e1; background: #ffffff; color: #475569; border-radius: 6px; font-weight: 600; cursor: pointer;">Keep Reservation</button>
        <button type="submit" style="padding: 0.5rem 1.25rem; border: none; background: #dc2626; color: #ffffff; border-radius: 6px; font-weight: 600; cursor: pointer;">Confirm Cancellation</button>
      </div>
    </form>
  </dialog>

  @if($booking->service_type === \App\Domain\Booking\ServiceType::Rent)
    <!-- Rent Extension Modal -->
    <dialog id="extension-dialog" class="accessible-dialog" aria-labelledby="extension-dialog-heading">
      <div style="margin-bottom: 1.25rem;">
        <h2 id="extension-dialog-heading" style="font-size: 1.25rem; font-weight: 700; color: #0f172a; margin-bottom: 0.25rem;">Request Rental Hire Extension</h2>
        <p style="font-size: 0.875rem; color: #64748b; margin: 0;">Extend your self-drive vehicle hire duration.</p>
      </div>

      <form method="POST" action="{{ route('booking.status.extension', ['reference' => $booking->reference, 'token' => $token]) }}">
        @csrf

        <div style="margin-bottom: 1.25rem;">
          <label for="extra-days" style="display: block; font-size: 0.875rem; font-weight: 600; color: #1e293b; margin-bottom: 0.35rem;">Number of Extra Days:</label>
          <input type="number" id="extra-days" name="extra_days" min="1" max="30" value="1" required style="width: 100%; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 1rem;">
        </div>

        <div style="margin-bottom: 1.25rem;">
          <label for="extension-notes" style="display: block; font-size: 0.875rem; font-weight: 600; color: #1e293b; margin-bottom: 0.35rem;">Return Location or Timing Notes (Optional):</label>
          <textarea id="extension-notes" name="notes" rows="2" placeholder="e.g. Will return at Colombo airport instead of hotel..." style="width: 100%; padding: 0.5rem; border: 1px solid #cbd5e1; border-radius: 6px; font-size: 0.875rem;"></textarea>
        </div>

        <div style="display: flex; justify-content: flex-end; gap: 0.75rem;">
          <button type="button" onclick="closeModal('extension-dialog')" style="padding: 0.5rem 1rem; border: 1px solid #cbd5e1; background: #ffffff; color: #475569; border-radius: 6px; font-weight: 600; cursor: pointer;">Cancel</button>
          <button type="submit" style="padding: 0.5rem 1.25rem; border: none; background: #0284c7; color: #ffffff; border-radius: 6px; font-weight: 600; cursor: pointer;">Request Extension</button>
        </div>
      </form>
    </dialog>
  @endif
</main>

<style>
.accessible-dialog {
  border: 1px solid var(--color-border, #e2e8f0);
  border-radius: 12px;
  padding: 1.5rem;
  max-width: 32rem;
  width: 90vw;
  background: #ffffff;
  box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
  overscroll-behavior: contain;
}

.accessible-dialog::backdrop {
  background: rgba(15, 23, 42, 0.65);
  backdrop-filter: blur(4px);
}

.accessible-dialog[open] {
  animation: dialog-appear 180ms cubic-bezier(0.16, 1, 0.3, 1);
}

@keyframes dialog-appear {
  from {
    opacity: 0;
    transform: translateY(8px) scale(0.98);
  }
  to {
    opacity: 1;
    transform: translateY(0) scale(1);
  }
}
</style>

<script>
function openModal(id) {
  const el = document.getElementById(id);
  if (el && typeof el.showModal === 'function') {
    el.showModal();
  }
}

function closeModal(id) {
  const el = document.getElementById(id);
  if (el && typeof el.close === 'function') {
    el.close();
  }
}

// Close dialog when clicking directly on backdrop & prevent double submission
document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('dialog.accessible-dialog').forEach(function (dialog) {
    dialog.addEventListener('click', function (e) {
      if (e.target === dialog) {
        dialog.close();
      }
    });
  });

  document.querySelectorAll('dialog.accessible-dialog form').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      const submitBtn = form.querySelector('button[type="submit"]');
      if (submitBtn) {
        if (submitBtn.getAttribute('aria-disabled') === 'true') {
          e.preventDefault();
          return;
        }
        submitBtn.setAttribute('aria-disabled', 'true');
        submitBtn.style.opacity = '0.7';
        submitBtn.style.pointerEvents = 'none';
      }
    });
  });
});
</script>
@endsection
