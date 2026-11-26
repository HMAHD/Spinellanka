@extends('layouts.app')

@section('title', 'Connecting to Secure Payment Gateway — Spinellanka')
@section('meta_robots', 'noindex, nofollow')

@section('content')
<main id="main-content" style="min-height: 60vh; display: flex; align-items: center; justify-content: center; padding: 2rem 1rem;">
  <div style="max-width: 520px; width: 100%; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 12px; padding: 2.5rem; text-align: center; box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -2px rgba(0, 0, 0, 0.05);">
    
    <div style="margin-bottom: 1.5rem;">
      <div style="width: 56px; height: 56px; border-radius: 50%; background: #e0f2fe; color: #0284c7; display: inline-flex; align-items: center; justify-content: center; margin-bottom: 1rem;">
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true">
          <rect x="1" y="4" width="22" height="16" rx="2" ry="2"></rect>
          <line x1="1" y1="10" x2="23" y2="10"></line>
        </svg>
      </div>
      <h1 style="font-size: 1.35rem; font-weight: 700; color: #0f172a; margin: 0 0 0.5rem;">Transferring to Secure Payment Gateway</h1>
      <p style="font-size: 0.925rem; color: #64748b; margin: 0; line-height: 1.5;">
        Please do not close or refresh this browser window while we redirect you to the merchant checkout session.
      </p>
    </div>

    <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem 1.25rem; margin-bottom: 1.5rem; text-align: left;">
      <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-size: 0.875rem;">
        <span style="color: #64748b;">Booking Reference:</span>
        <strong style="color: #0f172a;">{{ $booking->reference }}</strong>
      </div>
      <div style="display: flex; justify-content: space-between; margin-bottom: 0.5rem; font-size: 0.875rem;">
        <span style="color: #64748b;">Proposal Version:</span>
        <span style="color: #0f172a; font-weight: 600;">v{{ $proposal->version }}</span>
      </div>
      <div style="display: flex; justify-content: space-between; font-size: 1rem; border-top: 1px solid #e2e8f0; padding-top: 0.5rem;">
        <span style="color: #0f172a; font-weight: 600;">Settlement Total:</span>
        <strong style="color: #16a34a; font-size: 1.15rem;">{{ $proposal->currency }} {{ number_format(($proposal->deposit_required ? $proposal->deposit_amount_minor_units : $proposal->total_amount_minor_units) / 100, 2) }}</strong>
      </div>
    </div>

    <!-- Gateway POST Form -->
    <form id="payment-gateway-form" method="{{ $session->httpMethod }}" action="{{ $session->checkoutUrl }}">
      @foreach($session->formFields as $key => $value)
        <input type="hidden" name="{{ $key }}" value="{{ $value }}">
      @endforeach

      <div style="margin-top: 1.5rem;">
        <button type="submit" class="btn btn-primary" style="background: #0284c7; color: #ffffff; padding: 0.75rem 1.75rem; border: none; border-radius: 6px; font-weight: 600; font-size: 0.95rem; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem;">
          <span>Continue to Gateway</span>
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><polyline points="9 18 15 12 9 6"></polyline></svg>
        </button>
      </div>
    </form>

    <div style="margin-top: 1.5rem; font-size: 0.775rem; color: #94a3b8; display: flex; align-items: center; justify-content: center; gap: 0.5rem;">
      <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
      <span>256-Bit TLS Encrypted &bull; 3-D Secure Verified &bull; Zero Card Data Stored on Spinellanka</span>
    </div>

  </div>
</main>

<script>
  // Automatic submission when client-side JavaScript is active
  document.addEventListener('DOMContentLoaded', function () {
    var form = document.getElementById('payment-gateway-form');
    if (form) {
      form.submit();
    }
  });
</script>
@endsection
